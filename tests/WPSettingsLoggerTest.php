<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Settings_Logger;

class Test_WP_Settings_With_Logging extends WP_Settings
{
    public function __construct(string $plugin_dir_path, string $log_dir = '')
    {
        $this->sections = array(
            'general_settings' => array(
                'name' => 'General Settings',
                'tab' => 'general',
                'callback' => '__return_false',
            ),
        );

        $this->settings = array(
            'sample_field' => new WP_Setting('sample_field', 'Sample Field', 'text', 'general', 'general_settings'),
        );

        $this->logging(array(
            'plugin_dir_path' => $plugin_dir_path,
            'log_dir' => $log_dir,
        ));

        parent::__construct('my-plugin');
    }
}

class WPSettingsLoggerTest extends WP_Settings_TestCase
{
    private $plugin_dir_path;
    private $uploads_dir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/wp-settings-logger-' . uniqid('', true);
        $this->plugin_dir_path = $base . '/plugin';
        $this->uploads_dir = $base . '/uploads';
        mkdir($this->plugin_dir_path, 0777, true);
        mkdir($this->uploads_dir, 0777, true);
        $GLOBALS['wp_test_upload_basedir'] = $this->uploads_dir;
        $_GET = array();
        $_POST = array();
    }

    protected function tearDown(): void
    {
        $_GET = array();
        $_POST = array();
        $this->delete_directory(dirname($this->plugin_dir_path));
        parent::tearDown();
    }

    /** The uploads subdirectory the logger is expected to pick by default. */
    private function expected_log_dir(): string
    {
        return $this->uploads_dir . '/my_plugin-logs-' . substr(wp_hash('my_plugin|wp-settings-logs'), 0, 16);
    }

    private function enable_logging(string $level = 'info'): WP_Settings_Logger
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', $level);

        return $settings->get_logger();
    }

    public function test_logger_writes_log_file_in_hashed_uploads_dir(): void
    {
        $logger = $this->enable_logging();

        $this->assertInstanceOf(WP_Settings_Logger::class, $logger);

        $logger->info('Settings saved', array('tab' => 'general'));

        $log_file = $this->expected_log_dir() . '/my_plugin-' . date('Y-m-d') . '.log';

        $this->assertSame($this->expected_log_dir(), $logger->get_log_dir());
        $this->assertFileExists($log_file);
        $this->assertStringContainsString('Settings saved', (string) file_get_contents($log_file));
        $this->assertStringContainsString('general', (string) file_get_contents($log_file));
        $this->assertDirectoryDoesNotExist($this->plugin_dir_path . '/logs');
    }

    public function test_log_dir_is_guarded_and_not_world_writable(): void
    {
        $logger = $this->enable_logging();
        $logger->info('Guard me');

        $dir = $this->expected_log_dir();

        $this->assertFileExists($dir . '/index.php');
        $this->assertFileExists($dir . '/.htaccess');
        $this->assertStringContainsString('Require all denied', (string) file_get_contents($dir . '/.htaccess'));
        $this->assertSame(0, (fileperms($dir) & 0002), 'log directory must not be world-writable');
    }

    public function test_guards_are_backfilled_into_an_existing_log_dir(): void
    {
        $dir = $this->expected_log_dir();
        mkdir($dir, 0755, true);

        $logger = $this->enable_logging();
        $logger->info('Backfill');

        $this->assertFileExists($dir . '/index.php');
        $this->assertFileExists($dir . '/.htaccess');
    }

    public function test_log_dir_config_overrides_the_default(): void
    {
        $custom = dirname($this->plugin_dir_path) . '/custom-logs';
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path, $custom);

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'info');

        $logger = $settings->get_logger();
        $logger->info('Custom dir');

        $this->assertSame($custom, $logger->get_log_dir());
        $this->assertFileExists($custom . '/my_plugin-' . date('Y-m-d') . '.log');
        $this->assertFileExists($custom . '/index.php');
    }

    public function test_falls_back_to_plugin_dir_when_uploads_are_unusable(): void
    {
        $GLOBALS['wp_test_upload_basedir'] = '';

        $logger = $this->enable_logging();
        $logger->info('No uploads');

        $this->assertSame($this->plugin_dir_path . '/logs', $logger->get_log_dir());
        $this->assertFileExists($this->plugin_dir_path . '/logs/my_plugin-' . date('Y-m-d') . '.log');
        $this->assertFileExists($this->plugin_dir_path . '/logs/.htaccess');
    }

    public function test_legacy_plugin_logs_are_moved_and_the_legacy_dir_removed(): void
    {
        $legacy = $this->plugin_dir_path . '/logs';
        mkdir($legacy, 0777, true);
        file_put_contents($legacy . '/my_plugin-2000-01-01.log', 'old entry');

        $logger = $this->enable_logging();
        $logger->info('New entry');

        $this->assertFileExists($this->expected_log_dir() . '/my_plugin-2000-01-01.log');
        $this->assertDirectoryDoesNotExist($legacy);
        $this->assertContains('my_plugin-2000-01-01.log', $logger->get_log_files());
    }

    public function test_legacy_logs_are_migrated_when_only_listing_files(): void
    {
        $legacy = $this->plugin_dir_path . '/logs';
        mkdir($legacy, 0777, true);
        file_put_contents($legacy . '/my_plugin-2000-01-01.log', 'old entry');

        $logger = $this->enable_logging();

        $this->assertSame(array('my_plugin-2000-01-01.log'), $logger->get_log_files());
        $this->assertDirectoryDoesNotExist($legacy);
    }

    public function test_legacy_dir_holding_other_files_is_guarded_not_removed(): void
    {
        $legacy = $this->plugin_dir_path . '/logs';
        mkdir($legacy, 0777, true);
        file_put_contents($legacy . '/my_plugin-2000-01-01.log', 'old entry');
        file_put_contents($legacy . '/notes.txt', 'not ours');

        $logger = $this->enable_logging();
        $logger->info('New entry');

        $this->assertDirectoryExists($legacy);
        $this->assertFileExists($legacy . '/notes.txt');
        $this->assertFileExists($legacy . '/index.php');
        $this->assertFileExists($legacy . '/.htaccess');
        $this->assertFileDoesNotExist($legacy . '/my_plugin-2000-01-01.log');
    }

    public function test_write_is_skipped_when_the_log_dir_cannot_be_created(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores directory permissions');
        }

        $readonly = dirname($this->plugin_dir_path) . '/readonly';
        mkdir($readonly, 0555, true);
        $GLOBALS['wp_test_upload_basedir'] = $readonly;

        $error_log = dirname($this->plugin_dir_path) . '/php-error.log';
        $previous = ini_get('error_log');
        ini_set('error_log', $error_log);

        try {
            $logger = $this->enable_logging();
            $logger->info('First');
            $logger->info('Second');
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            chmod($readonly, 0777);
        }

        $this->assertDirectoryDoesNotExist($logger->get_log_dir());

        $reported = file_exists($error_log) ? (string) file_get_contents($error_log) : '';

        $this->assertStringContainsString('file logging unavailable', $reported);
        $this->assertSame(1, substr_count($reported, 'file logging unavailable'));
    }

    public function test_logger_rotates_old_plugin_logs(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        mkdir($this->expected_log_dir(), 0755, true);
        $old_file = $this->expected_log_dir() . '/my_plugin-2000-01-01.log';
        file_put_contents($old_file, 'old log');
        touch($old_file, strtotime('-10 days'));

        $this->setOption('my_plugin_log_retention_days', 1);

        $logger->rotate_logs();

        $this->assertFileDoesNotExist($old_file);
    }

    public function test_logging_tab_fields_are_registered(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        // Simulate admin_init priority 0 (deferred logging append) firing before init().
        $settings->_append_logging_definitions_once();
        $settings->init();

        $fields = $this->getRegisteredSettingsFields();
        $sections = $this->getRegisteredSettingsSections();

        $this->assertArrayHasKey('my_plugin_logging_enabled_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_destination_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_level_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_retention_days_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_auto_refresh_field', $fields);
        $this->assertArrayHasKey('my_plugin_section_logging_settings', $sections);
    }

    public function test_logging_tab_renders_viewer(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'error');

        $logger->error('Viewer entry');

        $_GET['tab'] = 'logging';

        // Simulate admin_init priority 0 firing before menu_page_callback().
        $settings->_append_logging_definitions_once();

        ob_start();
        $settings->menu_page_callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('Log Viewer', $output);
        $this->assertStringContainsString('Viewer entry', $output);
        $this->assertStringContainsString('wps-log-refresh', $output);
    }

    public function test_ajax_clear_log_removes_plugin_logs(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'error');

        $logger->error('Clear me');

        $_POST['nonce'] = 'nonce-my_plugin_logging_clear';

        $result = $settings->ajax_clear_log();

        $this->assertTrue($result['success']);
        $this->assertSame(array(), $logger->get_log_files());
    }

    private function delete_directory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($item_path)) {
                $this->delete_directory($item_path);
            } elseif (file_exists($item_path)) {
                unlink($item_path);
            }
        }

        rmdir($path);
    }
}
