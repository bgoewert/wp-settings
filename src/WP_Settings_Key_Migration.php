<?php

namespace BGoewert\WP_Settings;

if (!defined('ABSPATH')) {
    die();
}

if (class_exists('BGoewert\\WP_Settings\\WP_Settings_Key_Migration')) {
    return;
}

/**
 * Retire the `{TEXT_DOMAIN}_KEY`/`_NONCE` constants that versions before 4.0.0 wrote into wp-config.php.
 *
 * PHP cannot undefine a constant, so the request that removes them still
 * resolves the old key. Salt-encrypted copies are staged in an option and
 * swapped in on the first request that loads without the constants; until
 * then the old ciphertext stays, and the site keeps working.
 */
class WP_Settings_Key_Migration
{
    /** Text domains whose staged values this request already checked. */
    private static $applied = array();

    /** @var string */
    private $text_domain;

    /** @var string */
    private $plugin_name;

    /** @var callable Returns the option names of the encrypted fields. */
    private $option_names;

    /** @var string */
    private $config_file;

    /** @var string */
    private $key_constant;

    /** @var string */
    private $nonce_constant;

    /**
     * @param string        $text_domain  The consumer's text domain; names the constants and the option.
     * @param string        $plugin_name  Shown in the notice.
     * @param callable|null $option_names Returns the option names of the encrypted fields. Called
     *                                    only when migrating, once the fields are defined.
     * @param string|null   $config_file  wp-config.php. Versions before 4.0.0 only ever wrote to ABSPATH.
     */
    public function __construct(string $text_domain, string $plugin_name = '', ?callable $option_names = null, ?string $config_file = null)
    {
        $normalized           = WP_Setting::normalize_text_domain($text_domain);
        $this->text_domain    = $text_domain;
        $this->plugin_name    = '' !== $plugin_name ? $plugin_name : $text_domain;
        $this->option_names   = $option_names ?? static fn() => array();
        $this->config_file    = $config_file ?? \ABSPATH . 'wp-config.php';
        $this->key_constant   = strtoupper($normalized . '_key');
        $this->nonce_constant = strtoupper($normalized . '_nonce');
    }

    /**
     * Hook the notice, the handler and the re-stage, and swap in anything already staged.
     */
    public function register(): void
    {
        $this->apply_staged();

        \add_action('admin_notices', array($this, 'render_notice'));
        \add_action('admin_post_' . $this->action(), array($this, 'handle'));
        \add_action('updated_option', array($this, 'restage'), 10, 3);
        \add_action('added_option', array($this, 'restage_added'), 10, 2);
    }

    /**
     * Swap in staged values for a text domain, at most once per request.
     *
     * Called from WP_Setting::get(), so a front-end read after the lines are
     * removed does not depend on an admin page having loaded first.
     */
    public static function apply_staged_for(string $text_domain): void
    {
        if (isset(self::$applied[$text_domain])) {
            return;
        }

        (new self($text_domain))->apply_staged();
    }

    /** @internal Lets tests start a fresh request. */
    public static function reset(): void
    {
        self::$applied = array();
    }

    /** The option holding staged ciphertext, then the outcome. Autoloaded so checking costs no query. */
    public function option(): string
    {
        return WP_Setting::normalize_text_domain($this->text_domain) . '_key_migration';
    }

    /** The admin-post action, and the nonce action guarding it. */
    public function action(): string
    {
        return WP_Setting::normalize_text_domain($this->text_domain) . '_migrate_to_salts';
    }

    /**
     * Whether either constant is still defined.
     */
    public function constants_defined(): bool
    {
        return defined($this->key_constant) || defined($this->nonce_constant);
    }

    /**
     * Stage salt-encrypted copies of every encrypted field, then remove the constants.
     *
     * @return array{status: string, option?: string} 'removed' when wp-config.php was rewritten,
     *                                                'manual' when the admin must delete the lines,
     *                                                'failed' when nothing changed.
     */
    public function migrate(): array
    {
        $salt = self::salt_encryption();

        if (null === $salt) {
            return array('status' => 'failed', 'option' => '');
        }

        $current = $this->encryption();
        $staged  = array();

        foreach ((array) call_user_func($this->option_names) as $option) {
            $stored = (string) \get_option($option, '');

            if ('' === $stored) {
                continue;
            }

            try {
                $plaintext = $current->decrypt($stored);
                $cipher    = $salt->encrypt($plaintext);
                $verified  = $salt->decrypt($cipher) === $plaintext;
            } catch (\Throwable $e) {
                $verified = false;
            }

            if (!$verified) {
                return array('status' => 'failed', 'option' => $option);
            }

            $staged[$option] = $cipher;
        }

        \update_option($this->option(), array('staged' => $staged), true);

        return array('status' => $this->remove_constants() ? 'removed' : 'manual');
    }

    /**
     * Swap the staged ciphertext in once the constants are gone.
     */
    public function apply_staged(): void
    {
        self::$applied[$this->text_domain] = true;

        if ($this->constants_defined()) {
            return;
        }

        $state = \get_option($this->option());

        if (!is_array($state) || !isset($state['staged']) || $this->defines_present()) {
            return;
        }

        // What WP_Setting decrypts with from here on, an environment variable included.
        $current = $this->encryption();
        $failed  = array();

        foreach ((array) $state['staged'] as $option => $cipher) {
            try {
                $current->decrypt($cipher);
            } catch (\Throwable $e) {
                $failed[] = $option;
                continue;
            }

            \update_option($option, $cipher);
        }

        \update_option($this->option(), array('done' => true, 'failed' => $failed), true);
    }

    /**
     * Re-stage a field saved while the migration waits on wp-config.php.
     *
     * Without this the staged copy is the previous value, and the swap would
     * silently roll the field back.
     *
     * @param string $option    Option name.
     * @param mixed  $old_value Previous value.
     * @param mixed  $value     New value, encrypted under the constants.
     */
    public function restage($option, $old_value, $value): void
    {
        if ($option === $this->option() || !$this->constants_defined()) {
            return;
        }

        $state = \get_option($this->option());

        if (!is_array($state) || !isset($state['staged'])) {
            return;
        }

        // A field empty at staging time is not in the staged list, but saving it now still needs a salt copy.
        if (!array_key_exists($option, $state['staged']) && !in_array($option, (array) call_user_func($this->option_names), true)) {
            return;
        }

        // Dropped on failure: the swap then reports it unreadable instead of rolling it back.
        unset($state['staged'][$option]);
        $salt = self::salt_encryption();

        try {
            if (null !== $salt && '' !== (string) $value) {
                $state['staged'][$option] = $salt->encrypt($this->encryption()->decrypt((string) $value));
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        \update_option($this->option(), $state, true);
    }

    /**
     * Re-stage a field saved for the first time while the migration waits.
     *
     * @param string $option Option name.
     * @param mixed  $value  New value.
     */
    public function restage_added($option, $value): void
    {
        $this->restage($option, null, $value);
    }

    /**
     * Handle the notice's button.
     */
    public function handle(): void
    {
        if (!$this->user_can_migrate()) {
            \wp_die(\esc_html__('Sorry, you are not allowed to do that.', $this->text_domain), 403);
        }

        \check_admin_referer($this->action());

        $result = $this->constants_defined() ? $this->migrate() : array('status' => 'removed');

        if ('failed' === $result['status']) {
            \update_option($this->option(), array('error' => $result['option'] ?? ''), true);
        }

        $referer = \wp_get_referer();
        \wp_safe_redirect($referer ? $referer : \admin_url());
        exit;
    }

    /**
     * Render the offer, the manual step, or the outcome.
     */
    public function render_notice(): void
    {
        if (!$this->user_can_migrate()) {
            return;
        }

        $state = \get_option($this->option());
        $state = is_array($state) ? $state : array();

        if (!empty($state['done']) && !$this->constants_defined()) {
            \delete_option($this->option());
            $this->render_outcome((array) $state['failed']);
            return;
        }

        if (!$this->constants_defined()) {
            return;
        }

        if (isset($state['staged'])) {
            $this->render_manual_step();
            return;
        }

        if (isset($state['error'])) {
            \delete_option($this->option());
            $this->render_error((string) $state['error']);
        }

        $this->render_offer();
    }

    private function render_offer(): void
    {
        $intro = sprintf(
            /* translators: 1: plugin name, 2: key constant, 3: nonce constant, 4: wp-config.php */
            \esc_html__('%1$s still encrypts its settings with %2$s and %3$s from %4$s. Current versions use the WordPress salts instead, so these constants are one more secret to expose.', $this->text_domain),
            \esc_html($this->plugin_name),
            '<code>' . \esc_html($this->key_constant) . '</code>',
            '<code>' . \esc_html($this->nonce_constant) . '</code>',
            '<code>wp-config.php</code>'
        );
        $rotation = sprintf(
            /* translators: 1: LOGGED_IN_KEY, 2: NONCE_KEY */
            \esc_html__('After migrating, rotating %1$s or %2$s makes the encrypted settings unreadable, and they have to be entered again.', $this->text_domain),
            '<code>LOGGED_IN_KEY</code>',
            '<code>NONCE_KEY</code>'
        );

        echo '<div class="notice notice-warning"><p>' . $intro . '</p><p>' . $rotation . '</p>';
        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . \esc_attr($this->action()) . '">';
        \wp_nonce_field($this->action());
        echo '<p><button type="submit" class="button button-primary">' . \esc_html__('Migrate to WordPress salts', $this->text_domain) . '</button></p>';
        echo '</form></div>';
    }

    /**
     * The lines to delete when wp-config.php could not be edited. The values are elided: the point is to stop showing them.
     */
    private function render_manual_step(): void
    {
        $lines = '';

        foreach (array($this->key_constant, $this->nonce_constant) as $constant) {
            if (defined($constant)) {
                $lines .= "define('" . $constant . "', '…');\n";
            }
        }

        $message = sprintf(
            /* translators: 1: plugin name, 2: wp-config.php */
            \esc_html__('%1$s could not edit %2$s. Delete these lines from it to finish moving to the WordPress salts. The current settings keep working until you do.', $this->text_domain),
            \esc_html($this->plugin_name),
            '<code>wp-config.php</code>'
        );

        echo '<div class="notice notice-warning"><p>' . $message . '</p><pre>' . \esc_html($lines) . '</pre></div>';
    }

    /**
     * @param string $option The field that would not decrypt, or '' when the salts are missing.
     */
    private function render_error(string $option): void
    {
        $message = '' === $option
            /* translators: 1: LOGGED_IN_KEY, 2: NONCE_KEY */
            ? sprintf(\esc_html__('Nothing was migrated: %1$s and %2$s are not defined.', $this->text_domain), '<code>LOGGED_IN_KEY</code>', '<code>NONCE_KEY</code>')
            /* translators: %s: option name */
            : sprintf(\esc_html__('Nothing was migrated: %s could not be decrypted. Re-enter it, then try again.', $this->text_domain), '<code>' . \esc_html($option) . '</code>');

        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }

    /**
     * @param string[] $failed Options that did not decrypt under the new key.
     */
    private function render_outcome(array $failed): void
    {
        if (empty($failed)) {
            $message = sprintf(
                /* translators: %s: plugin name */
                \esc_html__('%s now encrypts its settings with the WordPress salts.', $this->text_domain),
                \esc_html($this->plugin_name)
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            return;
        }

        $message = sprintf(
            /* translators: 1: plugin name, 2: option names */
            \esc_html__('%1$s moved to the WordPress salts, but %2$s did not decrypt under the new key. Re-enter it.', $this->text_domain),
            \esc_html($this->plugin_name),
            '<code>' . \esc_html(implode(', ', $failed)) . '</code>'
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }

    /**
     * Delete the define() lines versions before 4.0.0 wrote.
     *
     * @return bool True when no line for a defined constant is left.
     */
    private function remove_constants(): bool
    {
        if (!\wp_is_file_mod_allowed('wp_settings_key_migration') || !is_file($this->config_file) || !\wp_is_writable($this->config_file)) {
            return false;
        }

        $content = file_get_contents($this->config_file);

        if (false === $content) {
            return false;
        }

        $updated = $content;

        foreach (array($this->key_constant, $this->nonce_constant) as $constant) {
            $updated = preg_replace("/define\\(\\s*'" . preg_quote($constant, '/') . "',\\s*'[^']*'\\s*\\);[ \\t]*\\r?\\n?/", '', $updated);

            // A hand-edited define this pattern misses would leave the constant in place.
            if (null === $updated || (defined($constant) && false !== strpos($updated, $constant))) {
                return false;
            }
        }

        if ($updated !== $content && false === file_put_contents($this->config_file, $updated, LOCK_EX)) {
            return false;
        }

        // Otherwise the redirect can still run the cached copy that defines them.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->config_file, true);
        }

        return true;
    }

    /**
     * Whether wp-config.php still has a define() for either constant.
     *
     * A define() below the `require_once ... wp-settings.php` line only runs
     * after every plugin has loaded, so "not defined yet" is not "removed".
     * Reads whether the line is there, never its value.
     */
    private function defines_present(): bool
    {
        if (!is_file($this->config_file) || !is_readable($this->config_file)) {
            return false;
        }

        $content = (string) file_get_contents($this->config_file);
        $names   = preg_quote($this->key_constant, '/') . '|' . preg_quote($this->nonce_constant, '/');

        return 1 === preg_match('/define\(\s*[\'"](' . $names . ')[\'"]/', $content);
    }

    /**
     * Refused on multisite: wp-config.php is network-wide, and only this site's values would move.
     */
    private function user_can_migrate(): bool
    {
        return !\is_multisite() && \current_user_can('manage_options');
    }

    /** The handle WP_Setting resolves for this text domain. */
    private function encryption(): WP_Setting_Encryption
    {
        return new WP_Setting_Encryption($this->key_constant, $this->nonce_constant);
    }

    /**
     * A handle on the salts, as resolution falls through to them once the constants are gone.
     */
    private static function salt_encryption(): ?WP_Setting_Encryption
    {
        if (!defined('LOGGED_IN_KEY') || '' === (string) \LOGGED_IN_KEY || !defined('NONCE_KEY') || '' === (string) \NONCE_KEY) {
            return null;
        }

        return new WP_Setting_Encryption(null, null, null, null, null, (string) \LOGGED_IN_KEY, (string) \NONCE_KEY);
    }
}
