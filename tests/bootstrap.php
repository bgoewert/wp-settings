<?php

$root_dir = dirname(__DIR__);

// Define ABSPATH for WordPress compatibility
if (!defined("ABSPATH")) {
    define("ABSPATH", $root_dir . "/tests/fixtures/");
}

if (!is_dir(ABSPATH)) {
    mkdir(ABSPATH, 0777, true);
}

// Create stub wp-config for local testing
$config_file = ABSPATH . "wp-config.php";
if (!file_exists($config_file)) {
    file_put_contents($config_file, "<?php\n// Stub wp-config for tests.\n");
}

// Namespaced function stubs must be declared before the library is autoloaded.
require_once __DIR__ . "/namespace-stubs.php";

// Extensions the current test wants the library to believe are missing.
global $wp_test_disabled_extensions;
$wp_test_disabled_extensions = [];

/**
 * Run $callback as though $extension were not compiled into PHP.
 *
 * @param string   $extension Extension name, e.g. "sodium".
 * @param callable $callback  Code to run under the simulated build.
 * @return mixed The callback's return value.
 */
function wp_settings_test_without_extension(string $extension, callable $callback)
{
    global $wp_test_disabled_extensions;

    $previous = $wp_test_disabled_extensions;
    $wp_test_disabled_extensions[] = $extension;

    try {
        return $callback();
    } finally {
        $wp_test_disabled_extensions = $previous;
    }
}

// Test globals
global $wp_test_options,
    $wp_test_actions,
    $wp_test_filters,
    $wp_test_settings_fields,
    $wp_test_settings_sections,
    $wp_test_enqueued_scripts,
    $wp_test_registered_scripts,
    $wp_test_enqueued_styles,
    $wp_test_inline_scripts,
    $wp_test_inline_script_positions,
    $wp_test_current_screen_id,
    $wp_test_doing_it_wrong_calls,
    $wp_test_upload_basedir,
    $wp_test_upload_error;

function wp_settings_test_reset_stubs(): void
{
    global $wp_test_options,
        $wp_test_actions,
        $wp_test_filters,
        $wp_test_settings_fields,
        $wp_test_settings_sections,
        $wp_test_enqueued_scripts,
        $wp_test_registered_scripts,
        $wp_test_enqueued_styles,
        $wp_test_inline_scripts,
        $wp_test_inline_script_positions,
        $wp_test_current_screen_id,
        $wp_test_doing_it_wrong_calls,
        $wp_test_upload_basedir,
        $wp_test_upload_error;

    $wp_test_options = [];
    $wp_test_actions = [];
    $wp_test_filters = [];
    $wp_test_settings_fields = [];
    $wp_test_settings_sections = [];
    $wp_test_enqueued_scripts = [];
    $wp_test_registered_scripts = [];
    $wp_test_enqueued_styles = [];
    $wp_test_inline_scripts = [];
    $wp_test_inline_script_positions = [];
    $wp_test_current_screen_id = null;
    $wp_test_doing_it_wrong_calls = [];
    $wp_test_upload_basedir = "";
    $wp_test_upload_error = false;

    // Declared further down, once the class it builds exists.
    if (function_exists("wp_settings_test_reset_wpdb")) {
        wp_settings_test_reset_wpdb();
    }
}

wp_settings_test_reset_stubs();

// WordPress function stubs

if (!function_exists("get_option")) {
    function get_option($option, $default = false)
    {
        global $wp_test_options;
        return $wp_test_options[$option] ?? $default;
    }
}

if (!function_exists("update_option")) {
    function update_option($option, $value)
    {
        global $wp_test_options;
        $wp_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists("delete_option")) {
    function delete_option($option)
    {
        global $wp_test_options;
        if (!array_key_exists($option, $wp_test_options)) {
            return false;
        }
        unset($wp_test_options[$option]);
        return true;
    }
}

if (!function_exists("add_option")) {
    function add_option($option, $value = "")
    {
        global $wp_test_options;
        if (!array_key_exists($option, $wp_test_options)) {
            $wp_test_options[$option] = $value;
        }
        return true;
    }
}

if (!function_exists("add_action")) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_actions;
        $wp_test_actions[$hook][] = compact(
            "callback",
            "priority",
            "accepted_args",
        );
        return true;
    }
}

if (!function_exists("add_filter")) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $wp_test_filters;
        $wp_test_filters[$hook][] = compact(
            "callback",
            "priority",
            "accepted_args",
        );
        return true;
    }
}

if (!function_exists("register_setting")) {
    function register_setting($option_group, $option_name, $args = [])
    {
        global $wp_test_options;
        if (isset($args["default"]) && !isset($wp_test_options[$option_name])) {
            $wp_test_options[$option_name] = $args["default"];
        }
        return true;
    }
}

if (!function_exists("add_settings_field")) {
    function add_settings_field(
        $id,
        $title,
        $callback,
        $page,
        $section = "default",
        $args = [],
    ) {
        global $wp_test_settings_fields;
        $wp_test_settings_fields[$id] = compact(
            "id",
            "title",
            "callback",
            "page",
            "section",
            "args",
        );
        return true;
    }
}

if (!function_exists("add_settings_section")) {
    function add_settings_section($id, $title, $callback, $page, $args = [])
    {
        global $wp_test_settings_sections;
        $wp_test_settings_sections[$id] = compact(
            "id",
            "title",
            "callback",
            "page",
            "args",
        );
        return true;
    }
}

if (!function_exists("add_submenu_page")) {
    function add_submenu_page(
        $parent_slug,
        $page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $callback = "",
        $position = null,
    ) {
        return "settings_page_" . $menu_slug;
    }
}

if (!function_exists("wp_kses")) {
    function wp_kses($string, $allowed_html)
    {
        return $string;
    }
}

if (!function_exists("wp_kses_post")) {
    function wp_kses_post($string)
    {
        return $string;
    }
}

if (!function_exists("wp_editor")) {
    function wp_editor($content, $editor_id, $settings = [])
    {
        // Minimal stand-in for the WP rich text editor: emit a textarea so tests
        // can assert the richtext child rendered.
        $name = $settings["textarea_name"] ?? $editor_id;
        echo sprintf(
            '<textarea class="wp-editor-area" id="%s" name="%s">%s</textarea>',
            esc_attr($editor_id),
            esc_attr($name),
            esc_textarea($content)
        );
    }
}

if (!function_exists("esc_html")) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("esc_attr")) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("esc_textarea")) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("esc_url")) {
    function esc_url($url)
    {
        return htmlspecialchars((string) $url, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("__")) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists("esc_html__")) {
    function esc_html__($text, $domain = null)
    {
        return esc_html($text);
    }
}

if (!function_exists("esc_attr__")) {
    function esc_attr__($text, $domain = null)
    {
        return esc_attr($text);
    }
}

if (!function_exists("checked")) {
    function checked($checked, $current = true, $echo = true)
    {
        $result =
            (bool) $checked === (bool) $current ? 'checked="checked"' : "";
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists("selected")) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = $selected === $current ? 'selected="selected"' : "";
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists("sanitize_text_field")) {
    function sanitize_text_field($value)
    {
        if (!is_scalar($value)) {
            return "";
        }
        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', " ", $value);
        $value = trim($value);
        // Match WP core: strip percent-encoded sequences. Tests must reflect
        // this so the preserve_percent_encoded opt-in is exercised correctly.
        while (preg_match('/%[a-f0-9]{2}/i', $value, $m)) {
            $value = str_replace($m[0], '', $value);
        }
        return $value;
    }
}

if (!function_exists("sanitize_textarea_field")) {
    function sanitize_textarea_field($value)
    {
        if (!is_scalar($value)) {
            return "";
        }
        $value = (string) $value;
        $value = strip_tags($value);
        $value = trim($value);
        while (preg_match('/%[a-f0-9]{2}/i', $value, $m)) {
            $value = str_replace($m[0], '', $value);
        }
        return $value;
    }
}

if (!function_exists("wp_check_invalid_utf8")) {
    function wp_check_invalid_utf8($value)
    {
        return (string) $value;
    }
}

if (!function_exists("wp_strip_all_tags")) {
    function wp_strip_all_tags($value, $remove_breaks = false)
    {
        $value = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $value);
        $value = strip_tags($value);
        if ($remove_breaks) {
            $value = preg_replace('/[\r\n\t ]+/', ' ', $value);
        }
        return trim($value);
    }
}

if (!function_exists("sanitize_key")) {
    function sanitize_key($key)
    {
        $key = strtolower((string) $key);
        return preg_replace("/[^a-z0-9_\-]/", "", $key);
    }
}

if (!function_exists("sanitize_email")) {
    function sanitize_email($email)
    {
        $email = trim($email);
        // Remove all characters except letters, digits and !#$%&'*+-=?^_`{|}~@.[]
        $email = preg_replace(
            '/[^a-zA-Z0-9!#$%&\'*+\-=?^_`{|}~@.\[\]]/',
            "",
            $email,
        );
        return $email;
    }
}

if (!function_exists("esc_url_raw")) {
    function esc_url_raw($url, $protocols = null)
    {
        if (empty($url)) {
            return "";
        }

        $url = trim($url);
        $url = str_replace(" ", "%20", $url);

        // Remove any dangerous protocols
        $dangerous = ["javascript:", "data:", "vbscript:"];
        foreach ($dangerous as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return "";
            }
        }

        return $url;
    }
}

if (!function_exists("wp_unslash")) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map("stripslashes", $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists("current_user_can")) {
    function current_user_can($capability)
    {
        return true;
    }
}

if (!function_exists("check_admin_referer")) {
    function check_admin_referer($action, $name)
    {
        return true;
    }
}

if (!function_exists("wp_verify_nonce")) {
    function wp_verify_nonce($nonce, $action)
    {
        return true;
    }
}

if (!function_exists("wp_create_nonce")) {
    function wp_create_nonce($action)
    {
        return "nonce-" . $action;
    }
}

if (!function_exists("wp_nonce_field")) {
    function wp_nonce_field($action, $name)
    {
        echo '<input type="hidden" name="' .
            $name .
            '" value="nonce-' .
            $action .
            '">';
    }
}

if (!function_exists("settings_fields")) {
    function settings_fields($option_group)
    {
        echo '<input type="hidden" name="option_page" value="' .
            $option_group .
            '">';
    }
}

if (!function_exists("do_settings_sections")) {
    function do_settings_sections($page)
    {
        echo '<div data-settings-sections="' . $page . '"></div>';
    }
}

if (!function_exists("submit_button")) {
    function submit_button($text = "Save Changes")
    {
        echo '<button type="submit">' . $text . "</button>";
    }
}

if (!function_exists("settings_errors")) {
    function settings_errors($setting = "", $sanitize = false, $hide_on_update = false)
    {
        return null;
    }
}

if (!function_exists("add_settings_error")) {
    function add_settings_error($setting, $code, $message, $type = "error")
    {
        // No-op for tests
    }
}

if (!function_exists("wp_send_json_success")) {
    function wp_send_json_success($data = null)
    {
        return ["success" => true, "data" => $data];
    }
}

if (!function_exists("wp_send_json_error")) {
    function wp_send_json_error($data = null)
    {
        return ["success" => false, "data" => $data];
    }
}

if (!function_exists("admin_url")) {
    function admin_url($path = "")
    {
        return "http://example.com/wp-admin/" . ltrim($path, "/");
    }
}

if (!function_exists("wp_json_encode")) {
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
}

if (!function_exists("wp_enqueue_style")) {
    function wp_enqueue_style($handle, $src = "", $deps = [], $ver = false, $media = "all")
    {
        global $wp_test_enqueued_styles;
        $wp_test_enqueued_styles[] = $handle;
        return true;
    }
}

if (!function_exists("wp_enqueue_script")) {
    function wp_enqueue_script($handle, $src = "", $deps = [], $ver = false, $in_footer = false)
    {
        global $wp_test_enqueued_scripts;
        $wp_test_enqueued_scripts[] = $handle;
        return true;
    }
}

if (!function_exists("wp_register_script")) {
    function wp_register_script($handle, $src = false, $deps = [], $ver = false, $in_footer = false)
    {
        global $wp_test_registered_scripts;
        $wp_test_registered_scripts[] = $handle;
        return true;
    }
}

if (!function_exists("wp_add_inline_script")) {
    function wp_add_inline_script($handle, $data, $position = "after")
    {
        global $wp_test_inline_scripts, $wp_test_inline_script_positions;
        $wp_test_inline_scripts[$handle][] = $data;
        $wp_test_inline_script_positions[$handle][] = $position;
        return true;
    }
}

if (!function_exists("plugin_dir_url")) {
    function plugin_dir_url($file)
    {
        return "http://example.com/wp-content/plugins/wp-settings/src/";
    }
}

if (!function_exists("get_current_screen")) {
    function get_current_screen()
    {
        global $wp_test_current_screen_id;
        if ($wp_test_current_screen_id === null) {
            return null;
        }
        return (object) ["id" => $wp_test_current_screen_id];
    }
}

if (!function_exists("sanitize_title")) {
    function sanitize_title($title)
    {
        $title = strtolower((string) $title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        return trim((string) $title, '-');
    }
}

if (!function_exists("esc_js")) {
    function esc_js($text)
    {
        return addslashes((string) $text);
    }
}

if (!function_exists("wp_test_split_url")) {
    /** Split a url into [base, query array], the way the query-arg helpers need it. */
    function wp_test_split_url($url)
    {
        $url = (string) ($url ?: ($_SERVER["REQUEST_URI"] ?? "http://example.com"));
        $base = $url;
        $query = [];

        if (($pos = strpos($url, "?")) !== false) {
            $base = substr($url, 0, $pos);
            parse_str(substr($url, $pos + 1), $query);
        }

        return [$base, $query];
    }
}

if (!function_exists("add_query_arg")) {
    function add_query_arg($args, $url = "", $legacy_url = "")
    {
        // WordPress accepts both add_query_arg( $array, $url )
        // and add_query_arg( $key, $value, $url ).
        if (!is_array($args)) {
            $args = [$args => $url];
            $url = $legacy_url;
        }

        [$base, $query] = wp_test_split_url($url);
        $query = array_merge($query, $args);

        return $query === [] ? $base : $base . "?" . http_build_query($query);
    }
}

if (!function_exists("remove_query_arg")) {
    function remove_query_arg($keys, $url = "")
    {
        [$base, $query] = wp_test_split_url($url);

        foreach ((array) $keys as $key) {
            unset($query[$key]);
        }

        return $query === [] ? $base : $base . "?" . http_build_query($query);
    }
}

if (!function_exists("wp_get_referer")) {
    function wp_get_referer()
    {
        return "http://example.com/wp-admin/options.php";
    }
}

if (!function_exists("wp_safe_redirect")) {
    function wp_safe_redirect($location)
    {
        return $location;
    }
}

if (!function_exists("_doing_it_wrong")) {
    function _doing_it_wrong($function_name, $message, $version)
    {
        global $wp_test_doing_it_wrong_calls;
        $wp_test_doing_it_wrong_calls[] = compact(
            "function_name",
            "message",
            "version",
        );
    }
}

if (!function_exists("wp_upload_dir")) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false)
    {
        global $wp_test_upload_basedir, $wp_test_upload_error;

        return [
            "basedir" => (string) $wp_test_upload_basedir,
            "baseurl" => "https://example.test/wp-content/uploads",
            "error" => $wp_test_upload_error,
        ];
    }
}

if (!function_exists("wp_mkdir_p")) {
    function wp_mkdir_p($target)
    {
        if ($target === "" || is_dir($target)) {
            return is_dir($target);
        }

        // Core suppresses the warning and reports failure through the return value.
        return @mkdir($target, 0755, true);
    }
}

if (!function_exists("wp_hash")) {
    function wp_hash($data, $scheme = "auth")
    {
        return hash_hmac("md5", (string) $data, "test-salt-" . $scheme);
    }
}

if (!function_exists("get_plugin_data")) {
    function get_plugin_data($file, $data = false, $markup = false)
    {
        return [
            "Name" => "Test Plugin",
            "Version" => "1.0.0",
            "TextDomain" => "test-plugin",
        ];
    }
}

if (!defined("ARRAY_A")) {
    define("ARRAY_A", "ARRAY_A");
}

/**
 * In-memory stand-in for $wpdb, covering the statements the table storage issues.
 *
 * prepare() stashes its arguments behind a token rather than interpolating
 * them, so the fake reads values back exactly as they were passed instead of
 * unescaping JSON out of a SQL string.
 */
class WP_Settings_Test_WPDB
{
    public $prefix = "wp_";

    /** @var string[] Every statement issued, in order. */
    public $queries = [];

    /** @var array<string, array<string, array>> Table name => row id => columns. */
    public $tables = [];

    /** @var array<string, array> Token => prepared arguments. */
    protected $prepared = [];

    public function prepare($query, ...$args)
    {
        $token = "wps_prep_" . count($this->prepared);
        $this->prepared[$token] = $args;

        return $query . " /*{$token}*/";
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        $table = $this->table_of($sql);
        $args = $this->args_of($sql);

        if (stripos(ltrim($sql), "INSERT INTO") === 0) {
            $columns = $this->insert_columns_of($sql);
            $values = [];

            foreach ($columns as $index => $column) {
                $values[$column] = $args[$index] ?? "";
            }

            // The adapter writes the id first, whatever it is called.
            $row_id = reset($values);
            $stored = $this->tables[$table][$row_id] ?? null;

            if ($stored !== null) {
                // Columns left out of ON DUPLICATE KEY UPDATE keep what they had.
                $updates = $this->update_columns_of($sql);

                foreach ($values as $column => $value) {
                    if (!in_array($column, $updates, true)) {
                        $values[$column] = $stored[$column] ?? $value;
                    }
                }
            }

            $this->tables[$table][$row_id] = $values;

            return 1;
        }

        if (stripos(ltrim($sql), "DELETE FROM") === 0) {
            $this->tables[$table] = [];
            return 1;
        }

        return 1;
    }

    public function get_var($sql)
    {
        $this->queries[] = $sql;

        $table = $this->table_of($sql);
        $args = $this->args_of($sql);
        $row_id = $args[0] ?? "";

        return $this->tables[$table][$row_id]["data"] ?? null;
    }

    public function get_row($sql, $output = null)
    {
        $this->queries[] = $sql;

        $table = $this->table_of($sql);
        $args = $this->args_of($sql);
        $row_id = $args[0] ?? "";

        return $this->tables[$table][$row_id] ?? null;
    }

    public function get_results($sql, $output = null)
    {
        $this->queries[] = $sql;

        $rows = array_values($this->tables[$this->table_of($sql)] ?? []);
        $order = $this->order_columns_of($sql);

        usort($rows, function ($a, $b) use ($order) {
            $left = [];
            $right = [];

            foreach ($order as $column) {
                $left[] = $a[$column] ?? "";
                $right[] = $b[$column] ?? "";
            }

            return $left <=> $right;
        });

        return $rows;
    }

    public function delete($table, $where, $formats = null)
    {
        $this->queries[] = "DELETE ROW `{$table}`";

        $row_id = reset($where);
        if (!isset($this->tables[$table][$row_id])) {
            return 0;
        }

        unset($this->tables[$table][$row_id]);
        return 1;
    }

    public function get_charset_collate()
    {
        return "DEFAULT CHARSET=utf8mb4";
    }

    /**
     * Create the table dbDelta was handed, if it does not exist yet.
     */
    public function create_table($name)
    {
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = [];
        }
    }

    protected function table_of($sql)
    {
        return preg_match("/`([a-z0-9_]+)`/i", $sql, $matches) ? $matches[1] : "";
    }

    /** Column names in an INSERT's column list, in order. */
    protected function insert_columns_of($sql)
    {
        preg_match('/\(([^)]*)\)\s*VALUES/is', $sql, $matches);

        return array_map("trim", explode(",", $matches[1] ?? ""));
    }

    /** Column names an ON DUPLICATE KEY UPDATE clause writes. */
    protected function update_columns_of($sql)
    {
        preg_match_all(
            '/([a-z0-9_]+)\s*=\s*VALUES\(/i',
            (string) strstr($sql, "ON DUPLICATE KEY UPDATE"),
            $matches,
        );

        return $matches[1];
    }

    /** Column names in an ORDER BY clause, in order. */
    protected function order_columns_of($sql)
    {
        if (!preg_match('/ORDER BY (.+?)(?:\s+LIMIT|\s*$)/is', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (explode(",", $matches[1]) as $term) {
            $columns[] = trim(preg_replace('/\s+(ASC|DESC).*$/i', "", trim($term)));
        }

        return $columns;
    }

    protected function args_of($sql)
    {
        return preg_match('/\/\*(wps_prep_\d+)\*\//', $sql, $matches)
            ? $this->prepared[$matches[1]] ?? []
            : [];
    }
}

global $wpdb, $wp_test_dbdelta_queries;

function wp_settings_test_reset_wpdb(): void
{
    global $wpdb, $wp_test_dbdelta_queries;

    $wpdb = new WP_Settings_Test_WPDB();
    $wp_test_dbdelta_queries = [];
}

wp_settings_test_reset_wpdb();

if (!function_exists("dbDelta")) {
    function dbDelta($queries = "", $execute = true)
    {
        global $wpdb, $wp_test_dbdelta_queries;

        foreach ((array) $queries as $query) {
            $wp_test_dbdelta_queries[] = $query;

            if (preg_match("/CREATE TABLE `([a-z0-9_]+)`/i", $query, $matches)) {
                $wpdb->create_table($matches[1]);
            }
        }

        return [];
    }
}

// Load composer autoloader
$autoload = $root_dir . "/vendor/autoload.php";
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

    /**
     * Retrieve every _doing_it_wrong() call recorded since the last reset.
     *
     * @return array
     */
    protected function getDoingItWrongCalls(): array
    {
        global $wp_test_doing_it_wrong_calls;
        return $wp_test_doing_it_wrong_calls ?? [];
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

    /**
     * Directly set an option value in the test environment.
     * This bypasses any WordPress option handling and is useful for setting up test conditions.
     * @param string $option The name of the option to set.
     * @param mixed $value The value to set for the option.
     * @return void
     */
    protected function setOption(string $option, $value): void
    {
        global $wp_test_options;
        $wp_test_options[$option] = $value;
    }

    /**
     * Retrieve an option value from the test environment.
     * This bypasses any WordPress option handling and is useful for verifying test conditions.
     * @param string $option The name of the option to retrieve.
     * @param mixed $default The default value to return if the option is not set.
     * @return mixed The value of the option or the default if not set.
     */
    protected function getOption(string $option, $default = false): mixed
    {
        global $wp_test_options;
        return $wp_test_options[$option] ?? $default;
    }

    protected function getEnqueuedScripts(): array
    {
        global $wp_test_enqueued_scripts;
        return $wp_test_enqueued_scripts ?? [];
    }

    protected function getRegisteredScripts(): array
    {
        global $wp_test_registered_scripts;
        return $wp_test_registered_scripts ?? [];
    }

    protected function getEnqueuedStyles(): array
    {
        global $wp_test_enqueued_styles;
        return $wp_test_enqueued_styles ?? [];
    }

    protected function getInlineScripts(): array
    {
        global $wp_test_inline_scripts;
        return $wp_test_inline_scripts ?? [];
    }

    protected function getInlineScriptPositions(): array
    {
        global $wp_test_inline_script_positions;
        return $wp_test_inline_script_positions ?? [];
    }

    protected function setCurrentScreen(?string $id): void
    {
        global $wp_test_current_screen_id;
        $wp_test_current_screen_id = $id;
    }
}
