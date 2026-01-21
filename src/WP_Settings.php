<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
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
     * Initialize plugin settings.
     *
     * @param array|string $plugin_data Either plugin data array with 'Name' and 'TextDomain' keys,
     *                                   or a simple text domain string.
     */
    protected function __construct($plugin_data)
    {
        // Support both plugin data array and simple text domain string
        if (is_string($plugin_data)) {
            // Convert text domain to a friendly name
            $name = ucwords(str_replace(['-', '_'], ' ', $plugin_data));
            $this->plugin_data = [
                'Name' => $name,
                'TextDomain' => $plugin_data,
            ];
            $this->text_domain = $plugin_data;
        } else {
            $this->plugin_data = $plugin_data;
            $this->text_domain = $plugin_data['TextDomain'];
        }

        // Set static text_domain for all WP_Setting instances
        WP_Setting::$text_domain = $this->text_domain;

        new WP_Setting_Encryption(
            strtoupper(str_replace('-', '_', $this->text_domain . '_key')),
            strtoupper(str_replace('-', '_', $this->text_domain . '_nonce'))
        );

        \add_action('admin_init', array($this, 'init'));
        \add_action('admin_menu', array($this, 'admin_menu'));
        \add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_admin'));
        \add_option($this->text_domain . '_key', base64_encode(WP_Setting::random_bytes(32)));
    }

    /**
     * Init the admin page.
     *
     * Automatically prevents duplicate menu registration by checking if a submenu
     * with the same slug already exists under the same parent menu.
     */
    public function admin_menu()
    {
        global $submenu;

        $slug = $this->text_domain;
        $parent = 'options-general.php';

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
            $this->plugin_data['Name'],
            $this->plugin_data['Name'],
            'manage_options',
            $slug,
            array($this, 'menu_page_callback')
        );

        \add_action('load-' . $this->submenu_page_hook, array($this, 'load_menu_screen'));
    }

    /**
     * Init all the options/settings.
     */
    public function init()
    {
        foreach ($this->sections as $section) {
            // translators: Placeholder is for the settings section name. This should have already been defined. This can be ignored.
            \add_settings_section($this->text_domain . '_section_' . $section['slug'], $section['name'], $section['callback'], $this->text_domain . '_' . $section['tab']);
        }

        foreach ($this->settings as $setting) {
            $setting->init();

            // Initialize child settings for advanced field types
            if ($setting->type === 'advanced' && !empty($setting->children)) {
                foreach ($setting->children as $child) {
                    $child->init();
                }
            }
        }
    }

    /**
     * Callback for the plugin's admin page.
     */
    public function menu_page_callback()
    {
        // Check user capabilities.
        if (!\current_user_can('manage_options')) {
            return;
        }

        // Get the active tab.
        $tab = isset($_GET['tab']) ? \sanitize_text_field(\wp_unslash($_GET['tab'])) : $this->sections[0]['tab'];

        // Get all tabs
        $tabs = array();
        $tab_labels = array();
        foreach ($this->sections as $section) {
            if (in_array($section['tab'], $tabs, true)) {
                continue;
            } else {
                $tabs[] = $section['tab'];
                $tab_labels[$section['tab']] = $section['tab_name']
                    ?? $section['tab_title']
                    ?? ucwords($section['tab']);
            }
        }

        $tab_label = $tab_labels[$tab] ?? ucwords($tab);

        // Check if the user have submitted the settings.
        // WordPress will add the "settings-updated" $_GET parameter to the url.
        if (isset($_GET['settings-updated']) && \check_admin_referer('update', 'sbp_nonce')) {
            // Add settings saved message with the class of "updated".
            \add_settings_error($this->text_domain . '_messages', $this->text_domain . '_message', $tab_label . ' Saved', 'updated');
        }

        if (isset($_POST['submit']) && \check_admin_referer('update', 'sbp_nonce')) {

            try {
                // Save all the settings.
                foreach ($this->settings as $setting) $setting->save();

                \add_settings_error($this->text_domain . '_messages', $this->text_domain . '_message', $tab_label . ' settings saved', 'updated');
            } catch (\Throwable $th) {
                \add_settings_error($this->text_domain . '_messages', $this->text_domain . '_message', $tab_label . ' failed to save.');
            }
        }
?>
        <h1 style="display:inline-block;"><?php echo \esc_html($this->plugin_data['Name']); ?></h1>

        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $t) : ?>
                <a href="?page=<?php echo rawurlencode($this->text_domain); ?>&tab=<?php echo $t ?>" class="nav-tab <?php echo ($t === $tab ? ' nav-tab-active' : ''); ?>"><?php echo \esc_html($tab_labels[$t] ?? ucwords($t)) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="tab-content">

            <form method="post" class="<?php echo \esc_html($this->text_domain . '-' . $tab); ?>" <?= ('settings' === $tab) ? ' enctype="multipart/form-data"' : '' ?>>
                <?php
                \wp_nonce_field('update', 'sbp_nonce');
                \settings_fields($this->text_domain . '_' . $tab);
                \do_settings_sections($this->text_domain . '_' . $tab);
                \submit_button('Save');
                ?>
            </form>
    <?php
    }

    /**
     * Load table list screens.
     */
    public function load_menu_screen() {}

    /**
     * Set the screen options for settings pages/tabs.
     *
     * @param mixed  $status The value to save instead of the option value.
     * @param string $option The option name.
     * @param int    $value The option value.
     */
    public function set_screen_option($status, $option, $value)
    {
        return $value;
    }

    /**
     * Get all the settings.
     */
    public function get_settings()
    {
        return $this->settings;
    }

    /**
     * Enqueue the styles/scripts for the admin panel.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin($hook)
    {
        // Only load on the plugin's admin page.
        if ($hook !== $this->submenu_page_hook) {
            return;
        }

        // Enqueue library CSS
        \wp_enqueue_style(
            'wp-settings-admin',
            \plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Empty section callback because it required for some reason.
     */
    public function empty_section_callback() {}
}
