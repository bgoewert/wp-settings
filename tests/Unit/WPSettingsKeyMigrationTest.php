<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Setting_Encryption;
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Settings_Key_Migration;

class Test_WP_Settings_Key_Migration_Page extends WP_Settings
{
    public function __construct(string $text_domain, array $settings)
    {
        $this->settings = $settings;
        parent::__construct($text_domain);
    }

    public function names(): array
    {
        return $this->encrypted_option_names();
    }
}

/**
 * Tests for retiring the `{TEXT_DOMAIN}_KEY`/`_NONCE` constants.
 *
 * Constants cannot be undefined, so each test takes its own text domain, and a
 * second domain whose constants nothing defines stands in for the request after
 * wp-config.php was edited.
 */
class WPSettingsKeyMigrationTest extends WP_Settings_TestCase
{
    private string $domain;
    private string $key_constant;
    private string $nonce_constant;
    private string $option_name;
    private string $config_file;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Settings_Key_Migration::reset();

        if (!defined('LOGGED_IN_KEY')) {
            define('LOGGED_IN_KEY', 'unit-test-logged-in-key-unit-test-logged-in-key');
        }
        if (!defined('NONCE_KEY')) {
            define('NONCE_KEY', 'unit-test-nonce-key-unit-test-nonce-key');
        }

        $this->domain         = 'wps-mig-' . bin2hex(random_bytes(4));
        $normalized           = strtoupper(WP_Setting::normalize_text_domain($this->domain));
        $this->key_constant   = $normalized . '_KEY';
        $this->nonce_constant = $normalized . '_NONCE';
        define($this->key_constant, base64_encode(random_bytes(32)));
        define($this->nonce_constant, base64_encode(random_bytes(24)));

        // Named under the "gone" domain, so WP_Setting::get() resolves it after the swap.
        $this->option_name = WP_Setting::normalize_text_domain($this->domain . '-gone') . '_secret';

        $this->config_file = tempnam(sys_get_temp_dir(), 'wp-config');
        file_put_contents(
            $this->config_file,
            "<?php\ndefine( 'DB_NAME', 'wordpress' );\n"
            . "define('" . $this->key_constant . "', '" . constant($this->key_constant) . "');\n"
            . "define('" . $this->nonce_constant . "', '" . constant($this->nonce_constant) . "');\n"
            . "require_once ABSPATH . 'wp-settings.php';\n"
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->config_file)) {
            unlink($this->config_file);
        }
        parent::tearDown();
    }

    private function migration(?string $config_file = null): WP_Settings_Key_Migration
    {
        return new WP_Settings_Key_Migration($this->domain, 'Test Plugin', fn() => array($this->option_name), $config_file ?? $this->config_file);
    }

    /** The same migration on the request after the constants were removed. */
    private function after_removal(): WP_Settings_Key_Migration
    {
        $after = new WP_Settings_Key_Migration($this->domain . '-gone', 'Test Plugin', fn() => array($this->option_name), $this->config_file);
        update_option($after->option(), get_option($this->migration()->option()));

        return $after;
    }

    private function store_under_constants(string $plaintext): void
    {
        update_option($this->option_name, (new WP_Setting_Encryption($this->key_constant, $this->nonce_constant))->encrypt($plaintext));
    }

    private function decrypt_with_salts(string $cipher): string
    {
        return (new WP_Setting_Encryption(null, null, null, null, null, LOGGED_IN_KEY, NONCE_KEY))->decrypt($cipher);
    }

    private function notice(WP_Settings_Key_Migration $migration): string
    {
        ob_start();
        $migration->render_notice();

        return html_entity_decode((string) ob_get_clean(), ENT_QUOTES);
    }

    public function test_migrate_stages_salt_ciphertext_and_removes_only_the_two_defines(): void
    {
        $this->store_under_constants('consumer-secret');
        $before = get_option($this->option_name);

        $this->assertSame(array('status' => 'removed'), $this->migration()->migrate());

        $this->assertSame($before, get_option($this->option_name), 'the live value waits for the constants to go');
        $state = get_option($this->migration()->option());
        $this->assertSame('consumer-secret', $this->decrypt_with_salts($state['staged'][$this->option_name]));
        $this->assertSame("<?php\ndefine( 'DB_NAME', 'wordpress' );\nrequire_once ABSPATH . 'wp-settings.php';\n", file_get_contents($this->config_file));
    }

    public function test_a_missing_config_file_leaves_the_deletion_to_the_admin(): void
    {
        $this->store_under_constants('consumer-secret');

        $this->assertSame('manual', $this->migration($this->config_file . '.missing')->migrate()['status']);
        $this->assertArrayHasKey('staged', get_option($this->migration()->option()));
    }

    public function test_disallowed_file_mods_leave_wp_config_alone(): void
    {
        $GLOBALS['wp_test_file_mods_allowed'] = false;
        $config = file_get_contents($this->config_file);

        $this->assertSame('manual', $this->migration()->migrate()['status']);
        $this->assertSame($config, file_get_contents($this->config_file));
    }

    public function test_an_unrecognized_define_is_not_half_removed(): void
    {
        $config = "<?php\ndefine( \"" . $this->key_constant . "\", getenv( 'K' ) );\ndefine('" . $this->nonce_constant . "', 'x');\n";
        file_put_contents($this->config_file, $config);

        $this->assertSame('manual', $this->migration()->migrate()['status']);
        $this->assertSame($config, file_get_contents($this->config_file));
    }

    public function test_an_undecryptable_field_stops_the_migration_and_is_named(): void
    {
        update_option($this->option_name, WP_Setting_Encryption::OPENSSL_PREFIX_V2 . '00000000:' . base64_encode(random_bytes(40)));
        $config = file_get_contents($this->config_file);

        $this->assertSame(array('status' => 'failed', 'option' => $this->option_name), $this->migration()->migrate());
        $this->assertFalse(get_option($this->migration()->option()));
        $this->assertSame($config, file_get_contents($this->config_file));
    }

    public function test_the_staged_value_is_swapped_in_once_the_constants_are_gone(): void
    {
        $this->store_under_constants('consumer-secret');
        $this->migration()->migrate();
        $after = $this->after_removal();

        $after->apply_staged();

        $this->assertSame('consumer-secret', $this->decrypt_with_salts(get_option($this->option_name)));
        $this->assertSame(array('done' => true, 'failed' => array()), get_option($after->option()));
        $this->assertStringContainsString('now encrypts its settings with the WordPress salts', $this->notice($after));
        $this->assertFalse(get_option($after->option()), 'the outcome is reported once');
    }

    public function test_nothing_is_swapped_while_wp_config_still_has_the_defines(): void
    {
        $this->store_under_constants('consumer-secret');
        $before = get_option($this->option_name);
        $this->migration($this->config_file . '.missing')->migrate();
        // A define() below the wp-settings.php require has not run yet while plugins load.
        file_put_contents($this->config_file, "<?php\nrequire_once ABSPATH . 'wp-settings.php';\ndefine('" . strtoupper(WP_Setting::normalize_text_domain($this->domain . '-gone')) . "_KEY', 'x');\n");

        $this->after_removal()->apply_staged();

        $this->assertSame($before, get_option($this->option_name));
    }

    public function test_nothing_is_swapped_while_the_constants_are_defined(): void
    {
        $this->store_under_constants('consumer-secret');
        $before = get_option($this->option_name);
        $this->migration($this->config_file . '.missing')->migrate();

        $this->migration()->apply_staged();

        $this->assertSame($before, get_option($this->option_name));
    }

    public function test_a_front_end_read_swaps_the_staged_value_in_first(): void
    {
        $this->store_under_constants('consumer-secret');
        $this->migration()->migrate();
        $this->after_removal();
        WP_Setting::$text_domain = $this->domain . '-gone';

        $this->assertSame('consumer-secret', WP_Setting::get('secret', '', true));
    }

    public function test_a_field_saved_mid_migration_replaces_the_staged_copy(): void
    {
        $this->store_under_constants('old-secret');
        $migration = $this->migration($this->config_file . '.missing');
        $migration->migrate();

        $this->store_under_constants('new-secret');
        $migration->restage($this->option_name, null, get_option($this->option_name));

        $state = get_option($migration->option());
        $this->assertSame('new-secret', $this->decrypt_with_salts($state['staged'][$this->option_name]));
    }

    public function test_a_field_first_saved_mid_migration_is_staged(): void
    {
        $migration = $this->migration($this->config_file . '.missing');
        $migration->migrate();

        $this->store_under_constants('first-secret');
        $migration->restage_added($this->option_name, get_option($this->option_name));

        $state = get_option($migration->option());
        $this->assertSame('first-secret', $this->decrypt_with_salts($state['staged'][$this->option_name]));
    }

    public function test_the_notice_offers_the_migration_to_administrators(): void
    {
        $html = $this->notice($this->migration());

        $this->assertStringContainsString('Migrate to WordPress salts', $html);
        $this->assertStringContainsString('value="' . $this->migration()->action() . '"', $html);
        $this->assertStringContainsString('LOGGED_IN_KEY', $html, 'warns that rotating the salts orphans the values');

        $GLOBALS['wp_test_denied_capabilities'] = array('manage_options');
        $this->assertSame('', $this->notice($this->migration()));
    }

    public function test_the_migration_is_refused_on_multisite(): void
    {
        $GLOBALS['wp_test_multisite'] = true;

        $this->assertSame('', $this->notice($this->migration()));
        $this->expectException(\RuntimeException::class);
        $this->migration()->handle();
    }

    public function test_the_notice_is_silent_without_the_constants(): void
    {
        $this->assertSame('', $this->notice(new WP_Settings_Key_Migration($this->domain . '-gone')));
    }

    public function test_the_manual_step_names_the_lines_without_the_key(): void
    {
        $this->migration($this->config_file . '.missing')->migrate();

        $html = $this->notice($this->migration());

        $this->assertStringContainsString("define('" . $this->key_constant . "', '…');", $html);
        $this->assertStringNotContainsString(constant($this->key_constant), $html);
        $this->assertStringNotContainsString('Migrate to WordPress salts', $html);
    }

    public function test_a_settings_page_migrates_its_password_and_encrypted_fields(): void
    {
        // A field's slug takes its prefix from the text domain current when it is constructed.
        WP_Setting::$text_domain = $this->domain;
        $page = new Test_WP_Settings_Key_Migration_Page($this->domain, array(
            new WP_Setting('secret', 'Secret', 'password', 'general', 'general'),
            new WP_Setting('token', 'Token', 'text', 'general', 'general', null, null, false, null, null, array('encrypted' => true)),
            new WP_Setting('plain', 'Plain', 'text', 'general', 'general'),
        ));
        $prefix = WP_Setting::normalize_text_domain($this->domain);

        $this->assertSame(array($prefix . '_secret', $prefix . '_token'), $page->names());
        $this->assertNotEmpty($this->getRecordedActions('admin_post_' . $this->migration()->action()));
    }

    public function test_a_constant_shadowed_by_the_environment_is_logged_once_and_named_in_a_notice(): void
    {
        putenv($this->key_constant . '=' . base64_encode(random_bytes(32)));
        $log = tempnam(sys_get_temp_dir(), 'wps-log');
        $previous_log = ini_set('error_log', $log);

        try {
            $page = new Test_WP_Settings_Key_Migration_Page($this->domain, array());

            ob_start();
            $page->report_key_source_conflicts();
            $page->report_key_source_conflicts();
            $html = (string) ob_get_clean();

            $this->assertSame(2, substr_count($html, '<code>' . $this->key_constant . '</code>'), 'notice repeats per page');
            $this->assertStringNotContainsString($this->nonce_constant, $html);
            $this->assertSame(1, substr_count((string) file_get_contents($log), $this->key_constant), 'logged once');
            $this->assertStringNotContainsString(constant($this->key_constant), (string) file_get_contents($log));

            $GLOBALS['wp_test_denied_capabilities'] = array('manage_options');
            ob_start();
            $page->report_key_source_conflicts();
            $this->assertSame('', ob_get_clean());
        } finally {
            putenv($this->key_constant);
            ini_set('error_log', (string) $previous_log);
            unlink($log);
        }
    }
}
