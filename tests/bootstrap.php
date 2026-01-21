<?php

$root_dir = dirname(__DIR__);

// Define ABSPATH for WordPress compatibility
if (!defined('ABSPATH')) {
    define('ABSPATH', $root_dir . '/tests/fixtures/');
}

if (!is_dir(ABSPATH)) {
    mkdir(ABSPATH, 0777, true);
}

// Create stub wp-config for local testing
$config_file = ABSPATH . 'wp-config.php';
if (!file_exists($config_file)) {
    file_put_contents($config_file, "<?php\n// Stub wp-config for tests.\n");
}

// Test globals
global $wp_test_options, $wp_test_actions, $wp_test_filters, $wp_test_settings_fields, $wp_test_settings_sections;

function wp_settings_test_reset_stubs(): void
{
    global $wp_test_options, $wp_test_actions, $wp_test_filters, $wp_test_settings_fields, $wp_test_settings_sections;

    $wp_test_options = [];
    $wp_test_actions = [];
    $wp_test_filters = [];
    $wp_test_settings_fields = [];
    $wp_test_settings_sections = [];
}

wp_settings_test_reset_stubs();

// WordPress function stubs

if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        global $wp_test_options;
        return $wp_test_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value)
    {
        global $wp_test_options;
        $wp_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value = '')
    {
        global $wp_test_options;
        if (!array_key_exists($option, $wp_test_options)) {
            $wp_test_options[$option] = $value;
        }
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_actions;
        $wp_test_actions[$hook][] = compact('callback', 'priority', 'accepted_args');
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_filters;
        $wp_test_filters[$hook][] = compact('callback', 'priority', 'accepted_args');
        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = [])
    {
        global $wp_test_options;
        if (isset($args['default']) && !isset($wp_test_options[$option_name])) {
            $wp_test_options[$option_name] = $args['default'];
        }
        return true;
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = [])
    {
        global $wp_test_settings_fields;
        $wp_test_settings_fields[$id] = compact('id', 'title', 'callback', 'page', 'section', 'args');
        return true;
    }
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page)
    {
        global $wp_test_settings_sections;
        $wp_test_settings_sections[$id] = compact('id', 'title', 'callback', 'page');
        return true;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
    {
        return 'settings_page_' . $menu_slug;
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html)
    {
        return $string;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = ((bool) $checked === (bool) $current) ? 'checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = ($selected === $current) ? 'selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', ' ', $value);
        return trim($value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        $email = trim($email);
        // Remove all characters except letters, digits and !#$%&'*+-=?^_`{|}~@.[]
        $email = preg_replace('/[^a-zA-Z0-9!#$%&\'*+\-=?^_`{|}~@.\[\]]/', '', $email);
        return $email;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null)
    {
        if (empty($url)) {
            return '';
        }

        $url = trim($url);
        $url = str_replace(' ', '%20', $url);

        // Remove any dangerous protocols
        $dangerous = ['javascript:', 'data:', 'vbscript:'];
        foreach ($dangerous as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return '';
            }
        }

        return $url;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('stripslashes', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        return true;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action, $name)
    {
        return true;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $name)
    {
        echo '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">';
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group)
    {
        echo '<input type="hidden" name="option_page" value="' . $option_group . '">';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page)
    {
        echo '<div data-settings-sections="' . $page . '"></div>';
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = 'Save Changes')
    {
        echo '<button type="submit">' . $text . '</button>';
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error')
    {
        // No-op for tests
    }
}

if (!function_exists('get_plugin_data')) {
    function get_plugin_data($file, $data = false, $markup = false)
    {
        return [
            'Name' => 'Test Plugin',
            'Version' => '1.0.0',
            'TextDomain' => 'test-plugin',
        ];
    }
}

// Load composer autoloader
$autoload = $root_dir . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Base test case class
abstract class WP_Settings_TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_settings_test_reset_stubs();
    }

    protected function getRecordedActions(string $hook): array
    {
        global $wp_test_actions;
        return $wp_test_actions[$hook] ?? [];
    }

    protected function getRecordedFilters(string $hook): array
    {
        global $wp_test_filters;
        return $wp_test_filters[$hook] ?? [];
    }

    protected function getRegisteredSettingsFields(): array
    {
        global $wp_test_settings_fields;
        return $wp_test_settings_fields;
    }

    protected function getRegisteredSettingsSections(): array
    {
        global $wp_test_settings_sections;
        return $wp_test_settings_sections;
    }

    protected function setOption(string $option, $value): void
    {
        global $wp_test_options;
        $wp_test_options[$option] = $value;
    }

    protected function getOption(string $option, $default = false)
    {
        global $wp_test_options;
        return $wp_test_options[$option] ?? $default;
    }
}
