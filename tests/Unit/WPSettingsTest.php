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
    public function expose_has_conditional_sections(): bool        { return $this->has_conditional_sections(); }
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
    /** @var array Autoloaders registered by a test, unregistered in tearDown. */
    private $registered_loaders = [];

    /** @var array Fake package directories created by a test. */
    private $registered_copies = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure text_domain is consistent when WP_Settings are constructed
        // before the page object (which calls parent::__construct internally).
        WP_Setting::$text_domain = 'test_plugin';
    }

    protected function tearDown(): void
    {
        foreach ($this->registered_loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        foreach ($this->registered_copies as $package) {
            @unlink($package . '/composer.json');
            @rmdir($package . '/src');
            @rmdir($package);
        }

        $this->registered_loaders = [];
        $this->registered_copies = [];

        parent::tearDown();
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

    /**
     * The constructor runs on every request, including uncached frontend page
     * views. Writing an option there costs a query per request forever after,
     * and nothing reads this one — the encryption key comes from a wp-config
     * constant. See #8.
     */
    public function test_constructor_does_not_create_a_key_option(): void
    {
        new Test_WP_Settings_Exposer();
        $this->assertFalse($this->getOption('test_plugin_key', false));
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

    public function test_get_controlling_fields_includes_section_conditions(): void
    {
        $page = new Test_WP_Settings_Exposer([make_setting('provider', 'select')], [
            'vimeo' => [
                'name'       => 'Vimeo',
                'tab'        => 'general',
                'callback'   => '__return_false',
                'conditions' => [['field' => 'provider', 'operator' => 'equals', 'value' => 'vimeo']],
            ],
        ]);

        $this->assertSame(['test_plugin_provider'], $page->expose_get_controlling_fields());
    }

    // -------------------------------------------------------------------------
    // Condition field references
    // -------------------------------------------------------------------------

    /**
     * A condition names a field the way it was declared, but the field renders
     * under its prefixed slug — so an unresolved reference matches no input in
     * the browser and the field it guards never appears.
     */
    public function test_init_resolves_a_field_condition_to_the_input_name(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $page = $this->make_multi_tab_page([
            make_setting('enabled', 'checkbox'),
            make_conditional_setting('value', 'enabled'),
        ]);
        $page->init();

        $html = $this->render_registered_field('my_plugin_value');

        $this->assertStringContainsString('&quot;field&quot;:&quot;my_plugin_enabled&quot;', $html);
    }

    /** A reference already naming the input is left alone rather than prefixed twice. */
    public function test_init_leaves_a_condition_that_already_names_the_input_alone(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $page = $this->make_multi_tab_page([
            make_setting('enabled', 'checkbox'),
            make_conditional_setting('value', 'my_plugin_enabled'),
        ]);
        $page->init();
        $page->init();

        $html = $this->render_registered_field('my_plugin_value');

        $this->assertStringContainsString('&quot;field&quot;:&quot;my_plugin_enabled&quot;', $html);
        $this->assertStringNotContainsString('my_plugin_my_plugin_enabled', $html);
    }

    public function test_find_field_slug_leaves_an_already_prefixed_reference_alone(): void
    {
        $page = new Test_WP_Settings_Exposer([]);

        $this->assertSame('test_plugin_unknown', $page->expose_find_field_slug('test_plugin_unknown'));
    }

    // -------------------------------------------------------------------------
    // Conditional sections
    // -------------------------------------------------------------------------

    /** Build a page whose second section is shown only for a provider value. */
    private function make_conditional_section_page(): Test_WP_Settings_Exposer
    {
        return new Test_WP_Settings_Exposer([make_setting('provider', 'select')], [
            'general_settings' => [
                'name'     => 'General',
                'tab'      => 'general',
                'callback' => '__return_false',
            ],
            'vimeo_settings' => [
                'name'       => 'Vimeo',
                'tab'        => 'general',
                'callback'   => '__return_false',
                'conditions' => [['field' => 'provider', 'operator' => 'equals', 'value' => 'vimeo']],
            ],
        ]);
    }

    /**
     * The heading and the form-table both sit inside the wrapper, so hiding a
     * section that does not apply leaves no empty heading behind.
     */
    public function test_init_wraps_a_conditional_section_with_its_conditions(): void
    {
        $page = $this->make_conditional_section_page();
        $page->init();

        $args = $this->getRegisteredSettingsSections()['test_plugin_section_vimeo_settings']['args'];

        $this->assertStringContainsString('class="wps-section-wrapper"', $args['before_section']);
        $this->assertStringContainsString('data-section="vimeo_settings"', $args['before_section']);
        $this->assertStringContainsString(
            '&quot;field&quot;:&quot;test_plugin_provider&quot;',
            $args['before_section'],
            'The section condition must name the input the browser sees, not the declared field name.'
        );
        $this->assertSame('</div>', $args['after_section']);
    }

    /** Core sprintf()s before_section when a class is set, which a `%` in a value would break. */
    public function test_init_does_not_set_a_section_class(): void
    {
        $page = $this->make_conditional_section_page();
        $page->init();

        $args = $this->getRegisteredSettingsSections()['test_plugin_section_vimeo_settings']['args'];

        $this->assertArrayNotHasKey('section_class', $args);
    }

    public function test_init_leaves_a_section_without_conditions_unwrapped(): void
    {
        $page = $this->make_conditional_section_page();
        $page->init();

        $args = $this->getRegisteredSettingsSections()['test_plugin_section_general_settings']['args'];

        $this->assertSame([], $args);
    }

    public function test_has_conditional_sections_reports_a_section_condition(): void
    {
        $this->assertTrue($this->make_conditional_section_page()->expose_has_conditional_sections());
        $this->assertFalse((new Test_WP_Settings_Exposer([], [
            'general_settings' => ['name' => 'General', 'tab' => 'general', 'callback' => '__return_false'],
        ]))->expose_has_conditional_sections());
    }

    /** The script has to load for a page whose only condition is on a section. */
    public function test_enqueue_admin_loads_the_conditional_script_for_a_section_condition(): void
    {
        $page = $this->make_conditional_section_page();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('settings_page_test-plugin');

        $this->assertContains('wp-settings-admin', $this->getEnqueuedScripts());
        $this->assertStringContainsString(
            '"test_plugin_provider"',
            implode('', $this->getInlineScripts()['wp-settings-admin'] ?? [])
        );
    }

    /**
     * admin.js reads the controlling fields at parse time, not on ready, so
     * data printed after the tag arrives too late and nothing is ever bound.
     */
    public function test_enqueue_admin_prints_the_conditionals_before_the_script(): void
    {
        $page = $this->make_conditional_section_page();
        $page->set_submenu_hook('settings_page_test-plugin');
        $page->enqueue_admin('settings_page_test-plugin');

        $this->assertSame(
            ['before'],
            $this->getInlineScriptPositions()['wp-settings-admin'] ?? []
        );
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

    /**
     * Regression test: init() reads $section["slug"] with a `?? $key` fallback one
     * line above $section["name"], but historically read "name" without the same
     * fallback, raising an "Undefined array key" warning for sections registered
     * without a name.
     */
    public function test_init_does_not_warn_for_section_missing_name_key(): void
    {
        $sections = [
            'general' => [
                'tab'      => 'general',
                'callback' => '__return_false',
                // Intentionally no 'name' key.
            ],
        ];
        $page = new Test_WP_Settings_Exposer([], $sections);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING);

        try {
            $page->init();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings,
            'A settings section without a "name" key must not raise an "Undefined array key" warning.');

        $registered = $this->getRegisteredSettingsSections();
        $this->assertArrayHasKey('test_plugin_section_general', $registered);
        $this->assertSame('', $registered['test_plugin_section_general']['title']);
    }

    // -------------------------------------------------------------------------
    // Tab navigation
    // -------------------------------------------------------------------------

    /** Capture the admin page markup for a settings page object. */
    private function render_menu_page(WP_Settings $page): string
    {
        unset($_GET['tab'], $_GET['settings-updated'], $_POST['submit']);

        ob_start();
        try {
            $page->menu_page_callback();
        } catch (\Throwable $th) {
            ob_end_clean();
            throw $th;
        }

        return (string) ob_get_clean();
    }

    public function test_menu_page_omits_tab_nav_for_a_single_tab(): void
    {
        $page = new Test_WP_Settings_Exposer([], [
            'general' => [
                'name'     => 'General',
                'tab'      => 'general',
                'callback' => '__return_false',
            ],
        ]);

        $html = $this->render_menu_page($page);

        $this->assertStringNotContainsString('nav-tab-wrapper', $html,
            'A page with one tab must not render a tab strip.');
    }

    public function test_menu_page_omits_tab_nav_when_sections_share_a_tab(): void
    {
        $page = new Test_WP_Settings_Exposer([], [
            'general' => [
                'name'     => 'General',
                'tab'      => 'general',
                'callback' => '__return_false',
            ],
            'more' => [
                'name'     => 'More',
                'tab'      => 'general',
                'callback' => '__return_false',
            ],
        ]);

        $html = $this->render_menu_page($page);

        $this->assertStringNotContainsString('nav-tab-wrapper', $html,
            'Several sections on one tab still amount to a single tab.');
    }

    public function test_menu_page_renders_tab_nav_for_multiple_tabs(): void
    {
        $page = new Test_WP_Settings_Multi_Tab([]);

        $html = $this->render_menu_page($page);

        $this->assertStringContainsString('nav-tab-wrapper', $html);
        $this->assertStringContainsString('>General</a>', $html);
        $this->assertStringContainsString('>Advanced</a>', $html);
        $this->assertStringContainsString('class="nav-tab nav-tab-active"', $html,
            'The tab being viewed must still be marked active.');
    }

    // -------------------------------------------------------------------------
    // Integration: a reorderable repeater from registration to save (#19)
    // -------------------------------------------------------------------------

    /** A repeater whose row order is the data — the order questions are asked. */
    private function make_reorderable_repeater(string $name): WP_Setting
    {
        return new WP_Setting($name, 'Attendee Fields', 'repeater', 'general', 'general', null, null, false, null, null, [
            'reorder'  => true,
            'children' => [
                ['name' => 'label', 'label' => 'Label', 'type' => 'text'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['text' => 'Text', 'email' => 'Email']],
            ],
        ]);
    }

    /** Render the field the way do_settings_fields() does: through what init() registered. */
    private function render_registered_field(string $slug): string
    {
        $fields = $this->getRegisteredSettingsFields();
        $this->assertArrayHasKey($slug . '_field', $fields, 'The repeater must register a settings field.');

        ob_start();
        call_user_func($fields[$slug . '_field']['callback'], $fields[$slug . '_field']['args']);
        return (string) ob_get_clean();
    }

    public function test_registered_repeater_renders_move_controls_for_saved_rows(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $this->setOption('my_plugin_attendee_fields', json_encode([
            ['label' => 'Name', 'type' => 'text'],
            ['label' => 'Email', 'type' => 'email'],
        ]));

        $page = $this->make_multi_tab_page([$this->make_reorderable_repeater('attendee_fields')]);
        $page->init();

        $html = $this->render_registered_field('my_plugin_attendee_fields');

        $this->assertStringContainsString('aria-label="Move row 1 down"', $html);
        $this->assertStringContainsString('aria-label="Move row 2 up"', $html);
        $this->assertStringContainsString('aria-label="Move row 1 up" disabled', $html);
        $this->assertStringContainsString('aria-label="Move row 2 down" disabled', $html);
    }

    /**
     * The browser reorders the DOM and reserializes; what reaches the option is
     * the moved order, and it is the order the field renders back.
     */
    public function test_saving_a_reordered_repeater_stores_and_rerenders_the_new_order(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $this->setOption('my_plugin_attendee_fields', json_encode([
            ['label' => 'Name', 'type' => 'text'],
            ['label' => 'Email', 'type' => 'email'],
        ]));

        $page = $this->make_multi_tab_page([$this->make_reorderable_repeater('attendee_fields')]);
        $page->init();

        $_POST['my_plugin_attendee_fields'] = json_encode([
            ['label' => 'Email', 'type' => 'email'],
            ['label' => 'Name', 'type' => 'text'],
        ]);
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_attendee_fields']);

        $this->assertSame(
            [
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Name', 'type' => 'text'],
            ],
            $this->getOption('my_plugin_attendee_fields')
        );

        $html = $this->render_registered_field('my_plugin_attendee_fields');

        $this->assertLessThan(
            strpos($html, 'value="Name"'),
            strpos($html, 'value="Email"'),
            'The moved row must render first.'
        );
        $this->assertStringContainsString('aria-label="Label, row 1"', $html);
        $this->assertStringContainsString('aria-label="Label, row 2"', $html);
    }

    /** Reordering is opt-in; a repeater that does not ask for it is unchanged. */
    public function test_registered_repeater_without_reorder_has_no_move_controls(): void
    {
        WP_Setting::$text_domain = 'my_plugin';
        $repeater = new WP_Setting('plain_rows', 'Plain Rows', 'repeater', 'general', 'general', null, null, false, null, null, [
            'children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']],
        ]);

        $page = $this->make_multi_tab_page([$repeater]);
        $page->init();

        $html = $this->render_registered_field('my_plugin_plain_rows');

        $this->assertStringContainsString('wps-repeater-row', $html);
        $this->assertStringNotContainsString('data-move=', $html);
    }

    // -------------------------------------------------------------------------
    // Construction order: parent first, then the fields
    // -------------------------------------------------------------------------

    /**
     * Each WP_Setting fixes its option slug from WP_Setting::$text_domain at
     * construction, and only WP_Settings::__construct() sets that static. Build
     * the fields first and every option key is unprefixed while
     * WP_Setting::get() goes looking for the prefixed one — so the documented
     * order is a contract, not a style preference.
     */
    public function test_fields_built_after_the_parent_constructor_are_prefixed(): void
    {
        WP_Setting::$text_domain = null;

        $page = new Test_WP_Settings_Ordered('my-plugin');

        $this->assertSame('my_plugin_my_option', $page->get_settings()['my_option']->slug);
    }

    /** The inverted order is what the README used to show. Pinned so it stays wrong. */
    public function test_fields_built_before_the_parent_constructor_are_unprefixed(): void
    {
        WP_Setting::$text_domain = null;

        $page = new Test_WP_Settings_Unordered('my-plugin');

        $this->assertSame('my_option', $page->get_settings()['my_option']->slug);
    }

    /**
     * Register a fake vendored copy of the library and return its package dir.
     *
     * Mirrors what Composer registers: the package holds `src/`, and `src/` is
     * what the PSR-4 prefix points at.
     */
    private function registerCopy(string $name, ?string $version = '9.9.9'): string
    {
        $package = sys_get_temp_dir() . '/wps-copy-' . $name . '-' . getmypid();

        if (!is_dir($package . '/src')) {
            mkdir($package . '/src', 0777, true);
        }

        if (null !== $version) {
            file_put_contents($package . '/composer.json', json_encode(['version' => $version]));
        }

        $loader = new Test_WP_Settings_Fake_Class_Loader($package . '/src');
        spl_autoload_register([$loader, 'loadClass']);
        $this->registered_loaders[] = [$loader, 'loadClass'];
        $this->registered_copies[] = $package;

        return realpath($package);
    }

    /** The duplicate notice is what the copies produced, if any. */
    private function duplicateNotice(): ?string
    {
        (new ReflectionClass(WP_Settings::class))->setStaticPropertyValue('duplicate_copies_reported', false);
        WP_Settings::warn_duplicate_copies();

        foreach ($this->getDoingItWrongCalls() as $call) {
            if (str_contains($call['message'], 'unscoped copy')) {
                return $call['message'];
            }
        }

        return null;
    }

    /** Two consumers vendoring it unscoped is the case that silently swaps their options. */
    public function test_two_copies_are_reported_with_paths_and_versions(): void
    {
        $other = $this->registerCopy('other', '4.6.0');

        $message = $this->duplicateNotice();

        $this->assertNotNull($message);
        $this->assertStringContainsString($other, $message);
        $this->assertStringContainsString('v4.6.0', $message);
        $this->assertStringContainsString(realpath(dirname(__DIR__, 2)), $message);
    }

    /** One copy is the normal case and must stay quiet. */
    public function test_a_single_copy_is_not_reported(): void
    {
        $this->assertNull($this->duplicateNotice());
    }

    /** One directory reached through two autoloaders is still one copy. */
    public function test_the_same_directory_registered_twice_is_one_copy(): void
    {
        $loader = new Test_WP_Settings_Fake_Class_Loader(dirname(__DIR__, 2) . '/src');
        spl_autoload_register([$loader, 'loadClass']);
        $this->registered_loaders[] = [$loader, 'loadClass'];

        $this->assertNull($this->duplicateNotice());
    }

    /** A copy whose manifest is missing is still worth naming. */
    public function test_a_copy_without_a_readable_version_reports_unknown(): void
    {
        $other = $this->registerCopy('versionless', null);

        $message = $this->duplicateNotice();

        $this->assertNotNull($message);
        $this->assertStringContainsString($other . ' (unknown version)', $message);
    }

    /** The notice goes out once, however many times admin_init fires it. */
    public function test_the_notice_is_reported_once_per_request(): void
    {
        $this->registerCopy('once', '4.6.0');

        $this->duplicateNotice();
        WP_Settings::warn_duplicate_copies();

        $notices = array_filter(
            $this->getDoingItWrongCalls(),
            static fn($call) => str_contains($call['message'], 'unscoped copy')
        );

        $this->assertCount(1, $notices);
    }
}

/**
 * The shape the detection looks for: any autoloader object exposing Composer's
 * `getPrefixesPsr4()`. Registering a real ClassLoader would mean a second
 * vendor tree on disk.
 */
class Test_WP_Settings_Fake_Class_Loader
{
    private $source_dir;

    public function __construct(string $source_dir)
    {
        $this->source_dir = $source_dir;
    }

    public function getPrefixesPsr4(): array
    {
        return ['BGoewert\\WP_Settings\\' => [$this->source_dir]];
    }

    public function loadClass($class)
    {
        return null;
    }
}

/**
 * The documented order: parent constructor, then the fields.
 */
class Test_WP_Settings_Ordered extends WP_Settings
{
    public function __construct(string $domain)
    {
        parent::__construct($domain);

        $this->sections = ['general' => ['name' => 'General', 'tab' => 'general', 'callback' => '__return_false']];
        $this->settings = ['my_option' => new WP_Setting('my_option', 'My Option', 'text', 'general', 'general')];
    }
}

/**
 * The inverted order, kept only to pin what it produces.
 */
class Test_WP_Settings_Unordered extends WP_Settings
{
    public function __construct(string $domain)
    {
        $this->sections = ['general' => ['name' => 'General', 'tab' => 'general', 'callback' => '__return_false']];
        $this->settings = ['my_option' => new WP_Setting('my_option', 'My Option', 'text', 'general', 'general')];

        parent::__construct($domain);
    }
}
