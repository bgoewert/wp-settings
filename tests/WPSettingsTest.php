<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;

// ---------------------------------------------------------------------------
// Test subclasses
// ---------------------------------------------------------------------------

/**
 * Concrete WP_Settings subclass with two tabs for testing tab-isolated saves.
 */
class Test_WP_Settings_Multi_Tab extends WP_Settings
{
    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->sections = [
            'general' => [
                'name'     => 'General',
                'tab'      => 'general',
                'callback' => '__return_false',
            ],
            'advanced' => [
                'name'     => 'Advanced',
                'tab'      => 'advanced',
                'callback' => '__return_false',
            ],
        ];

        parent::__construct('my-plugin');
    }

    public function expose_save_tab(string $tab): void
    {
        $this->save_tab_settings($tab);
    }
}

/**
 * Exposes protected methods and writable protected properties for unit tests.
 */
class Test_WP_Settings_Exposer extends WP_Settings
{
    public function __construct(array $settings = [], array $sections = [], array $tables = [])
    {
        $this->settings = $settings;
        $this->sections = $sections;
        $this->tables   = $tables;
        parent::__construct('test-plugin');
    }

    public function set_submenu_hook(string $hook): void { $this->submenu_page_hook = $hook; }
    public function set_version(?string $v): void        { $this->version = $v; }
    public function set_footer_text(?string $t): void    { $this->footer_text = $t; }

    public function expose_has_password_settings(): bool           { return $this->has_password_settings(); }
    public function expose_has_sortable_settings(): bool           { return $this->has_sortable_settings(); }
    public function expose_has_conditional_settings(): bool        { return $this->has_conditional_settings(); }
    public function expose_has_settings_for_tab(string $tab): bool { return $this->has_settings_for_tab($tab); }
    public function expose_has_any_sections_for_tab(string $tab): bool { return $this->has_any_sections_for_tab($tab); }
    public function expose_find_field_slug(string $name): string   { return $this->find_field_slug($name); }
    public function expose_get_controlling_fields(): array         { return $this->get_controlling_fields(); }
}

/**
 * Subclass used to test the null + pre-set text_domain constructor path.
 */
class Test_WP_Settings_Predefined extends WP_Settings
{
    protected $text_domain = 'my_predefined_plugin';

    public function __construct()
    {
        $this->settings = [];
        $this->sections = [];
        parent::__construct(null);
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a basic WP_Setting (type defaults to 'text', page to 'general'). */
function make_setting(string $name, string $type = 'text', string $page = 'general'): WP_Setting
{
    return new WP_Setting($name, ucfirst($name), $type, $page, 'general');
}

/** Build a WP_Setting with a conditions arg, making has_conditions() return true. */
function make_conditional_setting(string $name, string $controlling_field): WP_Setting
{
    return new WP_Setting($name, ucfirst($name), 'text', 'general', 'general', null, null, false, null, null, [
        'conditions' => [['field' => $controlling_field, 'value' => '1']],
    ]);
}

/** Build an 'advanced' WP_Setting that wraps children. */
function make_advanced_setting(string $name, array $children): WP_Setting
{
    return new WP_Setting($name, ucfirst($name), 'advanced', 'general', 'general', null, null, false, null, null, [
        'children' => $children,
    ]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class WPSettingsTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure text_domain is consistent when WP_Settings are constructed
        // before the page object (which calls parent::__construct internally).
        WP_Setting::$text_domain = 'test_plugin';
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function test_constructor_string_sets_text_domain(): void
    {
        new Test_WP_Settings_Exposer();
        $this->assertSame('test_plugin', WP_Setting::$text_domain);
    }

    public function test_constructor_array_sets_text_domain(): void
    {
        new class extends WP_Settings {
            public function __construct()
            {
                $this->settings = [];
                $this->sections = [];
                parent::__construct(['Name' => 'My Plugin', 'TextDomain' => 'array-domain']);
            }
        };
        $this->assertSame('array_domain', WP_Setting::$text_domain);
    }

    public function test_constructor_null_with_predefined_text_domain(): void
    {
        new Test_WP_Settings_Predefined();
        $this->assertSame('my_predefined_plugin', WP_Setting::$text_domain);
    }

    public function test_constructor_invalid_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new class extends WP_Settings {
            public function __construct()
            {
                $this->settings = [];
                $this->sections = [];
                parent::__construct(null); // no text_domain property set → invalid
            }
        };
    }

    // -------------------------------------------------------------------------
    // get_settings() / get_logger()
    // -------------------------------------------------------------------------

    public function test_get_settings_returns_settings_array(): void
    {
        $s = make_setting('foo');
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertSame([$s], $page->get_settings());
    }

    public function test_get_logger_returns_null_by_default(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $this->assertNull($page->get_logger());
    }

    // -------------------------------------------------------------------------
    // set_screen_option()
    // -------------------------------------------------------------------------

    public function test_set_screen_option_returns_value(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $this->assertSame(42, $page->set_screen_option(false, 'per_page', 42));
    }

    // -------------------------------------------------------------------------
    // has_settings_for_tab()
    // -------------------------------------------------------------------------

    public function test_has_settings_for_tab_exact_match(): void
    {
        $s    = make_setting('name', 'text', 'general');
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertTrue($page->expose_has_settings_for_tab('general'));
    }

    public function test_has_settings_for_tab_prefixed_match(): void
    {
        // Page stored as 'test_plugin_general' must still match tab 'general'.
        $s    = make_setting('name', 'text', 'test_plugin_general');
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertTrue($page->expose_has_settings_for_tab('general'));
    }

    public function test_has_settings_for_tab_no_match(): void
    {
        $s    = make_setting('name', 'text', 'advanced');
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertFalse($page->expose_has_settings_for_tab('general'));
    }

    // -------------------------------------------------------------------------
    // has_any_sections_for_tab()
    // -------------------------------------------------------------------------

    public function test_has_any_sections_for_tab_true(): void
    {
        $sections = ['general' => ['name' => 'General', 'tab' => 'general', 'callback' => '__return_false']];
        $page     = new Test_WP_Settings_Exposer([], $sections);
        $this->assertTrue($page->expose_has_any_sections_for_tab('general'));
    }

    public function test_has_any_sections_for_tab_false(): void
    {
        $sections = ['general' => ['name' => 'General', 'tab' => 'general', 'callback' => '__return_false']];
        $page     = new Test_WP_Settings_Exposer([], $sections);
        $this->assertFalse($page->expose_has_any_sections_for_tab('advanced'));
    }

    // -------------------------------------------------------------------------
    // has_password_settings()
    // -------------------------------------------------------------------------

    public function test_has_password_settings_false_when_none(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('name', 'text')]);
        $this->assertFalse($page->expose_has_password_settings());
    }

    public function test_has_password_settings_true_for_direct_password(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('secret', 'password')]);
        $this->assertTrue($page->expose_has_password_settings());
    }

    public function test_has_password_settings_true_for_child_in_advanced(): void
    {
        $child    = make_setting('child_secret', 'password');
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$advanced]);
        $this->assertTrue($page->expose_has_password_settings());
    }

    public function test_has_password_settings_false_with_non_password_advanced_child(): void
    {
        $child    = make_setting('child_text', 'text');
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$advanced]);
        $this->assertFalse($page->expose_has_password_settings());
    }

    // -------------------------------------------------------------------------
    // has_sortable_settings()
    // -------------------------------------------------------------------------

    public function test_has_sortable_settings_false_when_none(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('name', 'text')]);
        $this->assertFalse($page->expose_has_sortable_settings());
    }

    public function test_has_sortable_settings_true_for_direct_sortable(): void
    {
        $s = new WP_Setting('order', 'Order', 'sortable', 'general', 'general', null, null, false, null, null, [
            'options' => ['a' => 'A', 'b' => 'B'],
        ]);
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertTrue($page->expose_has_sortable_settings());
    }

    public function test_has_sortable_settings_true_for_child_in_advanced(): void
    {
        $child = new WP_Setting('order', 'Order', 'sortable', 'general', 'general', null, null, false, null, null, [
            'options' => ['a' => 'A'],
        ]);
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$advanced]);
        $this->assertTrue($page->expose_has_sortable_settings());
    }

    // -------------------------------------------------------------------------
    // has_conditional_settings()
    // -------------------------------------------------------------------------

    public function test_has_conditional_settings_false_when_none(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('name', 'text')]);
        $this->assertFalse($page->expose_has_conditional_settings());
    }

    public function test_has_conditional_settings_true_for_direct_setting(): void
    {
        $s    = make_conditional_setting('dependent', 'toggle');
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertTrue($page->expose_has_conditional_settings());
    }

    public function test_has_conditional_settings_true_for_child_in_advanced(): void
    {
        $child    = make_conditional_setting('child_dep', 'toggle');
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$advanced]);
        $this->assertTrue($page->expose_has_conditional_settings());
    }

    // -------------------------------------------------------------------------
    // find_field_slug()
    // -------------------------------------------------------------------------

    public function test_find_field_slug_finds_top_level_setting(): void
    {
        $s    = make_setting('api_key'); // slug = test_plugin_api_key
        $page = new Test_WP_Settings_Exposer([$s]);
        $this->assertSame('test_plugin_api_key', $page->expose_find_field_slug('api_key'));
    }

    public function test_find_field_slug_finds_child_inside_advanced(): void
    {
        $child    = make_setting('inner_field'); // slug = test_plugin_inner_field
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$advanced]);
        $this->assertSame('test_plugin_inner_field', $page->expose_find_field_slug('inner_field'));
    }

    public function test_find_field_slug_falls_back_to_prefixed_name(): void
    {
        $page = new Test_WP_Settings_Exposer([]);
        // Field not registered → falls back to {text_domain}_{name}
        $this->assertSame('test_plugin_unknown', $page->expose_find_field_slug('unknown'));
    }

    // -------------------------------------------------------------------------
    // get_controlling_fields()
    // -------------------------------------------------------------------------

    public function test_get_controlling_fields_returns_slugs_from_conditions(): void
    {
        $toggle    = make_setting('enabled'); // slug = test_plugin_enabled
        $dependent = make_conditional_setting('value', 'enabled');
        $page      = new Test_WP_Settings_Exposer([$toggle, $dependent]);

        $this->assertSame(['test_plugin_enabled'], $page->expose_get_controlling_fields());
    }

    public function test_get_controlling_fields_deduplicates(): void
    {
        $toggle = make_setting('enabled');
        $dep1   = make_conditional_setting('value1', 'enabled');
        $dep2   = make_conditional_setting('value2', 'enabled');
        $page   = new Test_WP_Settings_Exposer([$toggle, $dep1, $dep2]);

        $this->assertSame(['test_plugin_enabled'], $page->expose_get_controlling_fields());
    }

    public function test_get_controlling_fields_includes_children_conditions(): void
    {
        $toggle   = make_setting('flag');
        $child    = make_conditional_setting('child_dep', 'flag');
        $advanced = make_advanced_setting('group', [$child]);
        $page     = new Test_WP_Settings_Exposer([$toggle, $advanced]);

        $this->assertContains('test_plugin_flag', $page->expose_get_controlling_fields());
    }

    // -------------------------------------------------------------------------
    // enqueue_admin()
    // -------------------------------------------------------------------------

    public function test_enqueue_admin_bails_when_hook_does_not_match(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('secret', 'password')]);
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('some_other_hook');

        $this->assertEmpty($this->getEnqueuedScripts());
        $this->assertEmpty($this->getEnqueuedStyles());
    }

    public function test_enqueue_admin_registers_password_toggle_script(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('secret', 'password')]);
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('settings_page_test-plugin');

        $this->assertContains('wps-password-toggle', $this->getRegisteredScripts());
        $this->assertContains('wps-password-toggle', $this->getEnqueuedScripts());
        $this->assertArrayHasKey('wps-password-toggle', $this->getInlineScripts());
    }

    public function test_enqueue_admin_enqueues_sortable_assets_when_needed(): void
    {
        $s = new WP_Setting('order', 'Order', 'sortable', 'general', 'general', null, null, false, null, null, [
            'options' => ['a' => 'A'],
        ]);
        $page = new Test_WP_Settings_Exposer([$s]);
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('settings_page_test-plugin');

        $this->assertContains('wp-settings-admin-sortable', $this->getEnqueuedScripts());
        $this->assertContains('wp-settings-admin-sortable', $this->getEnqueuedStyles());
    }

    public function test_enqueue_admin_enqueues_admin_assets_for_conditional_settings(): void
    {
        $s    = make_conditional_setting('value', 'toggle');
        $page = new Test_WP_Settings_Exposer([$s]);
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('settings_page_test-plugin');

        $this->assertContains('wp-settings-admin', $this->getEnqueuedScripts());
        $this->assertContains('wp-settings-admin', $this->getEnqueuedStyles());
        $this->assertArrayHasKey('wp-settings-admin', $this->getInlineScripts());
    }

    // -------------------------------------------------------------------------
    // admin_footer_text()
    // -------------------------------------------------------------------------

    public function test_admin_footer_text_returns_footer_text_on_plugin_page(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->set_footer_text('Made with love');
        $this->setCurrentScreen('settings_page_test-plugin');

        $this->assertSame('Made with love', $page->admin_footer_text('Original'));
    }

    public function test_admin_footer_text_returns_original_on_other_page(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->set_footer_text('Made with love');
        $this->setCurrentScreen('edit.php');

        $this->assertSame('Original', $page->admin_footer_text('Original'));
    }

    public function test_admin_footer_text_returns_empty_string_when_no_footer_text_set(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $this->setCurrentScreen('settings_page_test-plugin');

        $this->assertSame('', $page->admin_footer_text('Original'));
    }

    // -------------------------------------------------------------------------
    // admin_footer_version()
    // -------------------------------------------------------------------------

    public function test_admin_footer_version_returns_version_on_plugin_page(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->set_version('3.1.4');
        $this->setCurrentScreen('settings_page_test-plugin');

        $this->assertSame('Version 3.1.4', $page->admin_footer_version('Original'));
    }

    public function test_admin_footer_version_returns_original_on_other_page(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->set_version('3.1.4');
        $this->setCurrentScreen('edit.php');

        $this->assertSame('Original', $page->admin_footer_version('Original'));
    }

    public function test_admin_footer_version_returns_original_when_no_version(): void
    {
        $page = new Test_WP_Settings_Exposer();
        $page->set_submenu_hook('settings_page_test-plugin');
        $this->setCurrentScreen('settings_page_test-plugin');

        $this->assertSame('Original', $page->admin_footer_version('Original'));
    }

    // -------------------------------------------------------------------------
    // save_tab_settings() — tab isolation (existing tests preserved)
    // -------------------------------------------------------------------------

    private function make_multi_tab_page(array $settings): Test_WP_Settings_Multi_Tab
    {
        return new Test_WP_Settings_Multi_Tab($settings);
    }

    public function test_save_tab_only_saves_current_tab_settings(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $general  = new WP_Setting('site_name', 'Site Name', 'text', 'general', 'general');
        $advanced = new WP_Setting('debug_mode', 'Debug Mode', 'checkbox', 'advanced', 'advanced');

        $page = $this->make_multi_tab_page([$general, $advanced]);

        $this->setOption('my_plugin_debug_mode', true);

        $_POST['my_plugin_site_name'] = 'My Site';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_site_name']);

        $this->assertSame('My Site', $this->getOption('my_plugin_site_name'));
        $this->assertTrue($this->getOption('my_plugin_debug_mode'));
    }

    public function test_save_tab_does_not_save_other_tab_settings(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $general  = new WP_Setting('color', 'Color', 'text', 'general', 'general');
        $advanced = new WP_Setting('timeout', 'Timeout', 'text', 'advanced', 'advanced');

        $page = $this->make_multi_tab_page([$general, $advanced]);

        $this->setOption('my_plugin_timeout', 'original');

        $_POST['my_plugin_color'] = 'blue';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_color']);

        $this->assertSame('blue', $this->getOption('my_plugin_color'));
        $this->assertSame('original', $this->getOption('my_plugin_timeout'), 'Other-tab setting must not be overwritten');
    }

    public function test_save_tab_saves_all_settings_on_the_active_tab(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $field1 = new WP_Setting('first_name', 'First Name', 'text', 'general', 'general');
        $field2 = new WP_Setting('last_name',  'Last Name',  'text', 'general', 'general');
        $other  = new WP_Setting('api_key',    'API Key',    'text', 'advanced', 'advanced');

        $page = $this->make_multi_tab_page([$field1, $field2, $other]);

        $_POST['my_plugin_first_name'] = 'Jane';
        $_POST['my_plugin_last_name']  = 'Doe';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_first_name'], $_POST['my_plugin_last_name']);

        $this->assertSame('Jane', $this->getOption('my_plugin_first_name'));
        $this->assertSame('Doe', $this->getOption('my_plugin_last_name'));
        $this->assertFalse($this->getOption('my_plugin_api_key', false), 'Other-tab setting must not be written');
    }

    public function test_save_tab_matches_prefixed_page_value(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $prefixed = new WP_Setting('prefixed_field', 'Prefixed', 'text', 'my_plugin_general', 'general');
        $page = $this->make_multi_tab_page([$prefixed]);

        $_POST['my_plugin_prefixed_field'] = 'value';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_prefixed_field']);

        $this->assertSame('value', $this->getOption('my_plugin_prefixed_field'));
    }
}
