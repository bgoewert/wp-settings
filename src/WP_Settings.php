<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined("ABSPATH")) {
    die();
}

// Protect against redeclaration errors.
if (class_exists("BGoewert\\WP_Settings\\WP_Settings")) {
    return;
}

/**
 * Defines all the settings to be used.
 */
class WP_Settings
{
    /**
     * Array of defined settings.
     *
     * @var WP_Setting[]
     */
    protected $settings;

    /**
     * Array of setting sections.
     *
     * @var array
     */
    protected $sections;

    /**
     * Array of settings tables.
     *
     * @var WP_Settings_Table[]
     */
    protected $tables = [];

    /**
     * Parent admin page hook.
     *
     * @var string
     */
    protected $menu_page_hook;

    /**
     * Submenu page hook.
     *
     * @var string
     */
    protected $submenu_page_hook;

    /**
     * Plugin metadata.
     *
     * @var array
     */
    protected $plugin_data;

    /**
     * Plugin text domain.
     *
     * @var string
     */
    protected $text_domain;

    /**
     * Plugin version to display in footer.
     *
     * @var string|null
     */
    protected $version = null;

    /**
     * Custom footer text to display on left side of admin footer.
     *
     * @var string|null
     */
    protected $footer_text = null;

    protected $logging_config = array();

    protected $logger = null;

    protected $uses_builtin_logging_tab = false;

    /**
     * Initialize plugin settings.
     *
     * @param array|string $plugin_data Either plugin data array with 'Name' and 'TextDomain' keys,
     *                                   or a simple text domain string.
     */
    protected function __construct($plugin_data = null)
    {
        // Support both plugin data array and simple text domain string
        if (is_string($plugin_data)) {
            // Convert text domain to a friendly name
            $this->text_domain = WP_Setting::normalize_text_domain(
                $plugin_data,
            );
            $this->plugin_data = [
                "Name" => ucwords(str_replace(["-", "_"], " ", $plugin_data)),
                "TextDomain" => $this->text_domain,
            ];
        } elseif (is_array($plugin_data)) {
            $this->plugin_data = $plugin_data;
            $this->text_domain = WP_Setting::normalize_text_domain(
                $plugin_data["TextDomain"],
            );
        } elseif ($plugin_data === null && $this->text_domain) {
            // Allow using the class property if set by a child class before calling parent constructor
            $this->text_domain = WP_Setting::normalize_text_domain(
                $this->text_domain,
            );
            $this->plugin_data = [
                "Name" => ucwords(
                    str_replace(["-", "_"], " ", $this->text_domain),
                ),
                "TextDomain" => $this->text_domain,
            ];
        } else {
            throw new \InvalidArgumentException(
                "Invalid plugin data provided. Must be an array with Name and TextDomain keys, a string text domain, or null.",
            );
        }

        // Set static text_domain for all WP_Setting instances
        WP_Setting::$text_domain = $this->text_domain;
        new WP_Setting_Encryption(
            strtoupper($this->text_domain . "_key"),
            strtoupper($this->text_domain . "_nonce"),
        );
        $this->initialize_logging_support();

        \add_action("admin_init", [$this, "init"]);
        \add_action("admin_menu", [$this, "admin_menu"]);
        \add_filter("set-screen-option", [$this, "set_screen_option"], 10, 3);
        \add_action("admin_enqueue_scripts", [$this, "enqueue_admin"]);
        \add_filter("admin_footer_text", [$this, "admin_footer_text"], 11);
        \add_filter("update_footer", [$this, "admin_footer_version"], 11);
        \add_option(
            $this->text_domain . "_key",
            base64_encode(WP_Setting::random_bytes(32)),
        );
    }

    /**
     * Init the admin page.
     *
     * Automatically prevents duplicate menu registration by checking if a submenu
     * with the same slug already exists under the same parent menu.
     */
    public function admin_menu(): void
    {
        global $submenu;

        $slug = $this->text_domain;
        $parent = "options-general.php";

        // Check if a submenu with this slug already exists under this parent
        if (isset($submenu[$parent])) {
            foreach ($submenu[$parent] as $item) {
                // $item[2] is the menu slug (see WordPress core add_submenu_page)
                if (isset($item[2]) && $item[2] === $slug) {
                    // Menu already registered, skip to prevent duplicates
                    return;
                }
            }
        }

        // Safe to register - no duplicate found
        $this->submenu_page_hook = \add_submenu_page(
            $parent,
            $this->plugin_data["Name"],
            $this->plugin_data["Name"],
            "manage_options",
            $slug,
            [$this, "menu_page_callback"],
        );

        \add_action("load-" . $this->submenu_page_hook, [
            $this,
            "load_menu_screen",
        ]);
    }

    /**
     * Init all the options/settings.
     */
    public function init(): void
    {
        foreach ($this->sections as $key => $section) {
            // Use array key as slug if slug property not defined (backward compatible).
            $slug = $section["slug"] ?? $key;
            // translators: Placeholder is for the settings section name. This should have already been defined. This can be ignored.
            \add_settings_section(
                $this->text_domain . "_section_" . $slug,
                $section["name"],
                $section["callback"],
                $this->text_domain . "_" . $section["tab"],
            );
        }

        foreach ($this->settings as $setting) {
            $setting->init();
        }

        if (!empty($this->tables)) {
            foreach ($this->tables as $table) {
                if ($table instanceof WP_Settings_Table) {
                    $table->set_text_domain($this->text_domain);
                    if ($this->logger !== null) {
                        $table->set_logger($this->logger);
                    }
                    $table->init();
                }
            }
        }
    }

    /**
     * Callback for the plugin's admin page.
     */
    public function menu_page_callback(): void
    {
        // Check user capabilities.
        if (!\current_user_can("manage_options")) {
            return;
        }

        // Get the active tab.
        $first_section = reset($this->sections);
        $tab = isset($_GET["tab"])
            ? \sanitize_text_field(\wp_unslash($_GET["tab"]))
            : $first_section["tab"];

        // Get all tabs
        $tabs = [];
        $tab_labels = [];
        foreach ($this->sections as $section) {
            if (in_array($section["tab"], $tabs, true)) {
                continue;
            } else {
                $tabs[] = $section["tab"];
                $tab_labels[$section["tab"]] =
                    $section["tab_name"] ??
                    ($section["tab_title"] ?? ucwords($section["tab"]));
            }
        }

        $tab_label = $tab_labels[$tab] ?? ucwords($tab);

        // Check if the user have submitted the settings.
        // WordPress will add the "settings-updated" $_GET parameter to the url.
        if (
            isset($_GET["settings-updated"]) &&
            \check_admin_referer("update", "sbp_nonce")
        ) {
            // Add settings saved message with the class of "updated".
            \add_settings_error(
                $this->text_domain . "_messages",
                $this->text_domain . "_message",
                $tab_label . " Saved",
                "updated",
            );
        }

        if (
            isset($_POST["submit"]) &&
            \check_admin_referer("update", "sbp_nonce")
        ) {
            try {
                // Save all the settings.
                foreach ($this->settings as $setting) {
                    $setting->save();
                }

                \add_settings_error(
                    $this->text_domain . "_messages",
                    $this->text_domain . "_message",
                    $tab_label . " settings saved",
                    "updated",
                );
            } catch (\Throwable $th) {
                if ($this->logger !== null) {
                    $this->logger->error(
                        $tab_label . ' failed to save',
                        array('exception' => $th->getMessage(), 'tab' => $tab),
                    );
                }
                \add_settings_error(
                    $this->text_domain . "_messages",
                    $this->text_domain . "_message",
                    $tab_label . " failed to save.",
                );
            }
        }

        $table = $this->get_table_for_tab($tab);
        if ($table) {
            $table->handle_post($tab);
        }
        ?>
        <h1 style="display:inline-block;"><?php echo \esc_html(
            $this->plugin_data["Name"],
        ); ?></h1>

        <?php \settings_errors($this->text_domain . "_messages"); ?>

        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $t): ?>
                <a href="?page=<?php echo rawurlencode(
                    $this->text_domain,
                ); ?>&tab=<?php echo $t; ?>" class="nav-tab <?php echo $t ===
$tab
    ? " nav-tab-active"
    : ""; ?>"><?php echo \esc_html($tab_labels[$t] ?? ucwords($t)); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="tab-content">

            <?php if ($table): ?>
                <?php $table->render($this->text_domain, $tab); ?>
            <?php endif; ?>

            <?php
            $has_settings = $this->has_settings_for_tab($tab);
            $has_sections = $this->has_any_sections_for_tab($tab);
            ?>

            <?php if ($has_sections): ?>
                <?php if ($has_settings): ?>
                    <form method="post" class="<?php echo \esc_html(
                        $this->text_domain . "-" . $tab,
                    ); ?>" <?= "settings" === $tab
    ? ' enctype="multipart/form-data"'
    : "" ?>>
                        <?php
                        \wp_nonce_field("update", "sbp_nonce");
                        \settings_fields($this->text_domain . "_" . $tab);
                        \do_settings_sections($this->text_domain . "_" . $tab);
                        \submit_button("Save");
                        ?>
                    </form>
                <?php else: ?>
                    <?php \do_settings_sections(
                        $this->text_domain . "_" . $tab,
                    ); ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->should_render_logging_viewer($tab)): ?>
                <?php $this->render_logging_viewer(); ?>
            <?php endif; ?>
    <?php
    }

    public function logging(array $config): void
    {
        $this->logging_config = array_merge(
            array(
                'enabled' => true,
                'plugin_dir_path' => '',
                'tab_slug' => 'logging',
                'tab_name' => 'Logging',
                'retention_days_default' => 14,
                'auto_refresh_default' => 0,
                'default_level' => 'error',
            ),
            $config,
        );
    }

    public function get_logger()
    {
        return $this->logger;
    }

    /**
     * Load table list screens.
     */
    public function load_menu_screen(): void {}

    /**
     * Set the screen options for settings pages/tabs.
     *
     * @param mixed  $status The value to save instead of the option value.
     * @param string $option The option name.
     * @param int    $value The option value.
     * @return int The value to save as the option value.
     */
    public function set_screen_option($status, $option, $value): int
    {
        return $value;
    }

    /**
     * Get all the settings.
     * @return WP_Setting[] Array of settings.
     */
    public function get_settings(): array
    {
        return $this->settings;
    }

    /**
     * Enqueue the styles/scripts for the admin panel.
     * @param string $hook The current admin page.
     */
    public function enqueue_admin($hook): void
    {
        // Only load on the plugin's admin page.
        if ($hook !== $this->submenu_page_hook) {
            return;
        }

        $has_tables = !empty($this->tables);
        $has_conditionals = $this->has_conditional_settings();

        if ($has_tables || $has_conditionals) {
            \wp_enqueue_style(
                "wp-settings-admin",
                \plugin_dir_url(__FILE__) . "assets/admin.css",
                [],
                "1.0.0",
            );
            \wp_enqueue_script(
                "wp-settings-admin",
                \plugin_dir_url(__FILE__) . "assets/admin.js",
                ["jquery"],
                "1.0.0",
                true,
            );

            // If we have conditional settings (not in tables), add inline script for initialization.
            if ($has_conditionals) {
                $controlling_fields = $this->get_controlling_fields();
                \wp_add_inline_script(
                    "wp-settings-admin",
                    "window.wpsSettingsConditionals = " .
                        \wp_json_encode([
                            "controllingFields" => $controlling_fields,
                        ]) .
                        ";",
                );
            }
        }

        if ($this->has_password_settings()) {
            \wp_enqueue_script("wp-auth");
        }

        if ($this->has_sortable_settings()) {
            \wp_enqueue_style(
                "wp-settings-admin-sortable",
                \plugin_dir_url(__FILE__) . "assets/admin-sortable.css",
                [],
                "1.0.0",
            );
            \wp_enqueue_script(
                "wp-settings-admin-sortable",
                \plugin_dir_url(__FILE__) . "assets/admin-sortable.js",
                ["jquery", "jquery-ui-sortable"],
                "1.0.0",
                true,
            );
        }
    }

    /**
     * Get all field names that other fields depend on (controlling fields) from settings.
     *
     * @return array Array of unique controlling field slugs.
     */
    protected function get_controlling_fields()
    {
        $controlling_fields = [];

        foreach ($this->settings as $setting) {
            if (!$setting instanceof WP_Setting) {
                continue;
            }

            if ($setting->has_conditions()) {
                foreach ($setting->conditions as $condition) {
                    if (!empty($condition["field"])) {
                        // Try to find the full slug for this field.
                        $field_slug = $this->find_field_slug(
                            $condition["field"],
                        );
                        if (
                            $field_slug &&
                            !in_array($field_slug, $controlling_fields, true)
                        ) {
                            $controlling_fields[] = $field_slug;
                        }
                    }
                }
            }

            if ($setting->type === "advanced" && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    if (
                        $child instanceof WP_Setting &&
                        $child->has_conditions()
                    ) {
                        foreach ($child->conditions as $condition) {
                            if (!empty($condition["field"])) {
                                $field_slug = $this->find_field_slug(
                                    $condition["field"],
                                );
                                if (
                                    $field_slug &&
                                    !in_array(
                                        $field_slug,
                                        $controlling_fields,
                                        true,
                                    )
                                ) {
                                    $controlling_fields[] = $field_slug;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $controlling_fields;
    }

    /**
     * Find the full slug for a field by its name.
     *
     * @param string $field_name The field name to find.
     * @return string|null The full slug or null if not found.
     */
    protected function find_field_slug($field_name)
    {
        foreach ($this->settings as $setting) {
            if (!$setting instanceof WP_Setting) {
                continue;
            }

            if ($setting->name === $field_name) {
                return $setting->slug;
            }

            if ($setting->type === "advanced" && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    if (
                        $child instanceof WP_Setting &&
                        $child->name === $field_name
                    ) {
                        return $child->slug;
                    }
                }
            }
        }

        // If not found, return the field_name with text_domain prefix as fallback.
        return $this->text_domain . "_" . $field_name;
    }

    /**
     * Empty section callback because it required for some reason.
     */
    public function empty_section_callback() {}

    /**
     * Get a table for the active tab.
     *
     * @param string $tab Tab slug.
     * @return WP_Settings_Table|null
     */
    protected function get_table_for_tab($tab)
    {
        if (empty($this->tables)) {
            return null;
        }

        foreach ($this->tables as $table) {
            if (
                $table instanceof WP_Settings_Table &&
                $table->handles_tab($tab)
            ) {
                return $table;
            }
        }

        return null;
    }

    protected function initialize_logging_support(): void
    {
        if (!$this->is_logging_feature_enabled()) {
            return;
        }

        $this->logger = new WP_Settings_Logger(
            array(
                'plugin_dir_path' => $this->logging_config['plugin_dir_path'],
                'text_domain' => $this->text_domain,
                'default_level' => $this->logging_config['default_level'] ?? 'error',
            )
        );

        WP_Setting::set_logger($this->logger);

        if (!$this->has_logging_tab_conflict()) {
            $this->append_logging_definitions();
        }

        \add_action('wp_ajax_' . $this->get_logging_ajax_action('view'), array($this, 'ajax_view_log'));
        \add_action('wp_ajax_' . $this->get_logging_ajax_action('tail'), array($this, 'ajax_tail_log'));
        \add_action('wp_ajax_' . $this->get_logging_ajax_action('clear'), array($this, 'ajax_clear_log'));
    }

    protected function is_logging_feature_enabled(): bool
    {
        return !empty($this->logging_config['enabled']) && !empty($this->logging_config['plugin_dir_path']);
    }

    protected function get_logging_tab_slug(): string
    {
        return $this->logging_config['tab_slug'] ?? 'logging';
    }

    protected function get_logging_section_slug(): string
    {
        return $this->get_logging_tab_slug() . '_settings';
    }

    protected function get_logging_ajax_action(string $action): string
    {
        return $this->text_domain . '_logging_' . $action;
    }

    protected function get_logging_nonce_action(string $action): string
    {
        return $this->get_logging_ajax_action($action);
    }

    protected function has_logging_tab_conflict(): bool
    {
        if (empty($this->sections)) {
            return false;
        }

        foreach ($this->sections as $section) {
            if (($section['tab'] ?? '') === $this->get_logging_tab_slug()) {
                return true;
            }
        }

        return false;
    }

    protected function append_logging_definitions(): void
    {
        if (!is_array($this->sections)) {
            $this->sections = array();
        }

        if (!is_array($this->settings)) {
            $this->settings = array();
        }

        $tab_slug = $this->get_logging_tab_slug();
        $section_slug = $this->get_logging_section_slug();

        $this->sections[$section_slug] = array(
            'name' => 'Logging Settings',
            'tab' => $tab_slug,
            'tab_name' => $this->logging_config['tab_name'] ?? 'Logging',
            'callback' => array($this, 'empty_section_callback'),
        );
        $this->uses_builtin_logging_tab = true;

        $settings = array(
            'logging_enabled' => new WP_Setting('logging_enabled', 'Enable Logging', 'checkbox', $tab_slug, $section_slug, null, 'Enable file-based logging for this plugin.', false, ''),
            'log_destination' => new WP_Setting('log_destination', 'Log Destination', 'select', $tab_slug, $section_slug, '280px', 'Choose where new log entries are written.', false, 'plugin', null, array('options' => array('plugin' => 'Plugin Log File', 'wordpress' => 'WordPress Debug Log'))),
            'log_level' => new WP_Setting('log_level', 'Log Level', 'select', $tab_slug, $section_slug, '220px', 'Only messages at or above this level are written.', false, $this->logging_config['default_level'] ?? 'error', null, array('options' => array('error' => 'Error', 'warning' => 'Warning', 'info' => 'Info', 'debug' => 'Debug'))),
            'log_retention_days' => new WP_Setting('log_retention_days', 'Retention Days', 'number', $tab_slug, $section_slug, '120px', 'Delete plugin log files older than this many days.', false, (string) ($this->logging_config['retention_days_default'] ?? 14)),
            'log_auto_refresh' => new WP_Setting('log_auto_refresh', 'Auto Refresh', 'select', $tab_slug, $section_slug, '220px', 'Automatically refresh the log viewer on this interval.', false, (string) ($this->logging_config['auto_refresh_default'] ?? 0), null, array('options' => array('0' => 'Disabled', '2' => '2 seconds', '5' => '5 seconds', '10' => '10 seconds', '30' => '30 seconds'))),
        );

        foreach ($settings as $key => $setting) {
            if (!isset($this->settings[$key])) {
                $this->settings[$key] = $setting;
            }
        }
    }

    protected function should_render_logging_viewer($tab): bool
    {
        return $this->logger !== null && $this->uses_builtin_logging_tab && $tab === $this->get_logging_tab_slug();
    }

    protected function render_logging_viewer(): void
    {
        $files = $this->logger->get_log_files();
        $selected_file = isset($_GET['log_file']) ? \sanitize_text_field(\wp_unslash($_GET['log_file'])) : '';
        if ($selected_file === '' || !in_array($selected_file, $files, true)) {
            $selected_file = $files[0] ?? '';
        }

        $destination = $this->logger->get_destination();
        $contents = $destination === 'plugin'
            ? $this->logger->get_log_contents($selected_file, 200)
            : 'Log destination is set to WordPress debug log. Switch destination to Plugin Log File to use the built-in viewer.';
        $ajax_url = \admin_url('admin-ajax.php');
        $view_nonce = \wp_create_nonce($this->get_logging_nonce_action('view'));
        $clear_nonce = \wp_create_nonce($this->get_logging_nonce_action('clear'));
        $tab_slug = $this->get_logging_tab_slug();
        $refresh = $this->logger->get_auto_refresh_interval();
        ?>
        <hr>
        <h2><?php echo \esc_html__('Log Viewer', 'wp-settings'); ?></h2>
        <p><?php echo \esc_html($this->logger->get_log_dir()); ?></p>
        <div style="margin: 0 0 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <form method="get" style="display: inline-flex; gap: 8px; align-items: center;">
                <input type="hidden" name="page" value="<?php echo \esc_attr($this->text_domain); ?>">
                <input type="hidden" name="tab" value="<?php echo \esc_attr($tab_slug); ?>">
                <select name="log_file">
                    <option value=""><?php echo \esc_html__('Current Log', 'wp-settings'); ?></option>
                    <?php foreach ($files as $file): ?>
                        <option value="<?php echo \esc_attr($file); ?>"<?php echo \selected($selected_file, $file, false); ?>><?php echo \esc_html($file); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php echo \esc_html__('Open', 'wp-settings'); ?></button>
            </form>
            <button type="button" class="button" id="wps-log-refresh"><?php echo \esc_html__('Refresh', 'wp-settings'); ?></button>
            <button type="button" class="button" id="wps-log-clear"><?php echo \esc_html__('Clear Logs', 'wp-settings'); ?></button>
            <span id="wps-log-status"></span>
        </div>
        <pre id="wps-log-viewer" data-file="<?php echo \esc_attr($selected_file); ?>" style="background:#1e1e1e;color:#d4d4d4;padding:16px;max-height:480px;overflow:auto;white-space:pre-wrap;"><?php echo \esc_html($contents); ?></pre>
        <script>
            (function() {
                var viewer = document.getElementById('wps-log-viewer');
                var refreshButton = document.getElementById('wps-log-refresh');
                var clearButton = document.getElementById('wps-log-clear');
                var status = document.getElementById('wps-log-status');
                if (!viewer || !refreshButton || !clearButton) {
                    return;
                }

                function post(action, nonce, extra, done) {
                    var body = new URLSearchParams();
                    body.append('action', action);
                    body.append('nonce', nonce);
                    body.append('file', viewer.getAttribute('data-file') || '');
                    if (extra) {
                        Object.keys(extra).forEach(function(key) {
                            body.append(key, extra[key]);
                        });
                    }
                    fetch('<?php echo \esc_url($ajax_url); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    }).then(function(response) {
                        return response.json();
                    }).then(done);
                }

                refreshButton.addEventListener('click', function() {
                    status.textContent = 'Refreshing...';
                    post('<?php echo \esc_attr($this->get_logging_ajax_action('view')); ?>', '<?php echo \esc_attr($view_nonce); ?>', null, function(result) {
                        if (result && result.success && result.data) {
                            viewer.textContent = result.data.content || '';
                            status.textContent = 'Updated';
                        } else {
                            status.textContent = 'Refresh failed';
                        }
                    });
                });

                clearButton.addEventListener('click', function() {
                    status.textContent = 'Clearing...';
                    post('<?php echo \esc_attr($this->get_logging_ajax_action('clear')); ?>', '<?php echo \esc_attr($clear_nonce); ?>', null, function(result) {
                        if (result && result.success) {
                            viewer.textContent = '';
                            status.textContent = 'Cleared';
                        } else {
                            status.textContent = 'Clear failed';
                        }
                    });
                });

                <?php if ($destination === 'plugin' && $refresh > 0): ?>
                window.setInterval(function() {
                    refreshButton.click();
                }, <?php echo (int) $refresh * 1000; ?>);
                <?php endif; ?>
            }());
        </script>
        <?php
    }

    public function ajax_view_log()
    {
        if (!$this->can_manage_logging_request('view')) {
            return \wp_send_json_error(array('message' => __('Invalid logging request', 'wp-settings')));
        }

        $file = isset($_POST['file']) ? \sanitize_text_field(\wp_unslash($_POST['file'])) : '';

        return \wp_send_json_success(
            array(
                'content' => $this->logger->get_destination() === 'plugin' ? $this->logger->get_log_contents($file, 200) : '',
                'file' => $file,
                'file_size' => $this->logger->get_log_size($file),
                'files' => $this->logger->get_log_files(),
            )
        );
    }

    public function ajax_tail_log()
    {
        if (!$this->can_manage_logging_request('tail')) {
            return \wp_send_json_error(array('message' => __('Invalid logging request', 'wp-settings')));
        }

        $file = isset($_POST['file']) ? \sanitize_text_field(\wp_unslash($_POST['file'])) : '';
        $offset = isset($_POST['offset']) ? (int) \wp_unslash($_POST['offset']) : 0;

        return \wp_send_json_success($this->logger->get_log_tail($file, $offset));
    }

    public function ajax_clear_log()
    {
        if (!$this->can_manage_logging_request('clear')) {
            return \wp_send_json_error(array('message' => __('Invalid logging request', 'wp-settings')));
        }

        return \wp_send_json_success(array('cleared' => $this->logger->clear_logs()));
    }

    protected function can_manage_logging_request(string $action): bool
    {
        if ($this->logger === null || !\current_user_can('manage_options')) {
            return false;
        }

        if (!isset($_POST['nonce'])) {
            return false;
        }

        return \wp_verify_nonce(
            \sanitize_text_field(\wp_unslash($_POST['nonce'])),
            $this->get_logging_nonce_action($action)
        );
    }

    /**
     * Check if a tab has any actual settings (fields).
     *
     * @param string $tab Tab slug.
     * @return bool
     */
    protected function has_settings_for_tab($tab)
    {
        foreach ($this->settings as $setting) {
            // Check both prefixed and non-prefixed page values
            if (
                $setting->page === $tab ||
                $setting->page === $this->text_domain . "_" . $tab
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a tab has any sections (for rendering callbacks).
     *
     * @param string $tab Tab slug.
     * @return bool
     */
    protected function has_any_sections_for_tab($tab)
    {
        foreach ($this->sections as $section) {
            if ($section["tab"] === $tab) {
                return true;
            }
        }

        return false;
    }

    protected function has_password_settings()
    {
        foreach ($this->settings as $setting) {
            if (!$setting instanceof WP_Setting) {
                continue;
            }

            if ($setting->type === "password") {
                return true;
            }

            if ($setting->type === "advanced" && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    if ($child instanceof WP_Setting && $child->type === "password") {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function has_sortable_settings()
    {
        foreach ($this->settings as $setting) {
            if (!$setting instanceof WP_Setting) {
                continue;
            }

            if ($setting->type === "sortable") {
                return true;
            }

            if ($setting->type === "advanced" && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    if (
                        $child instanceof WP_Setting &&
                        $child->type === "sortable"
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if any settings have conditional visibility rules.
     *
     * @return bool
     */
    protected function has_conditional_settings()
    {
        foreach ($this->settings as $setting) {
            if (!$setting instanceof WP_Setting) {
                continue;
            }

            if ($setting->has_conditions()) {
                return true;
            }

            if ($setting->type === "advanced" && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    if (
                        $child instanceof WP_Setting &&
                        $child->has_conditions()
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Display custom footer text on left side of admin footer if on plugin's settings page.
     *
     * @param string $text The current footer text.
     * @return string Modified footer text.
     */
    public function admin_footer_text($text)
    {
        $current_screen = \get_current_screen();

        // On plugin's settings page, use custom footer_text (defaults to empty string)
        if (
            $current_screen &&
            $current_screen->id === $this->submenu_page_hook
        ) {
            return $this->footer_text ?? "";
        }

        return $text;
    }

    /**
     * Display plugin version in admin footer if on plugin's settings page.
     *
     * @param string $footer_text The current footer text.
     * @return string Modified footer text with version.
     */
    public function admin_footer_version($footer_text)
    {
        $current_screen = \get_current_screen();

        // Only show version on plugin's settings page
        if (
            $current_screen &&
            $current_screen->id === $this->submenu_page_hook &&
            !empty($this->version)
        ) {
            return sprintf(
                /* translators: %s: Plugin version number */
                \esc_html__("Version %s", "wp-settings"),
                \esc_html($this->version),
            );
        }

        return $footer_text;
    }
}
