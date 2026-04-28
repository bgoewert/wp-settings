<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;

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
 * Tests for WP_Settings (page-level class).
 */
class WPSettingsTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Setting::$text_domain = 'my-plugin';
    }

    private function make_page(array $settings): Test_WP_Settings_Multi_Tab
    {
        return new Test_WP_Settings_Multi_Tab($settings);
    }

    // -------------------------------------------------------------------------
    // save_tab_settings() — tab isolation
    // -------------------------------------------------------------------------

    public function test_save_tab_only_saves_current_tab_settings(): void
    {
        $general = new WP_Setting('site_name', 'Site Name', 'text', 'general', 'general');
        $advanced = new WP_Setting('debug_mode', 'Debug Mode', 'checkbox', 'advanced', 'advanced');

        $page = $this->make_page([$general, $advanced]);

        // Pre-seed the advanced option so we can detect if it gets clobbered
        $this->setOption('my_plugin_debug_mode', true);

        $_POST['my_plugin_site_name'] = 'My Site';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_site_name']);

        $this->assertSame('My Site', $this->getOption('my_plugin_site_name'));
        // Advanced setting must be untouched — not forced to false by a missing POST key
        $this->assertTrue($this->getOption('my_plugin_debug_mode'));
    }

    public function test_save_tab_does_not_save_other_tab_settings(): void
    {
        $general  = new WP_Setting('color', 'Color', 'text', 'general', 'general');
        $advanced = new WP_Setting('timeout', 'Timeout', 'text', 'advanced', 'advanced');

        $page = $this->make_page([$general, $advanced]);

        $this->setOption('my_plugin_timeout', 'original');

        $_POST['my_plugin_color'] = 'blue';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_color']);

        $this->assertSame('blue', $this->getOption('my_plugin_color'));
        $this->assertSame('original', $this->getOption('my_plugin_timeout'), 'Other-tab setting must not be overwritten');
    }

    public function test_save_tab_saves_all_settings_on_the_active_tab(): void
    {
        $field1 = new WP_Setting('first_name', 'First Name', 'text', 'general', 'general');
        $field2 = new WP_Setting('last_name',  'Last Name',  'text', 'general', 'general');
        $other  = new WP_Setting('api_key',    'API Key',    'text', 'advanced', 'advanced');

        $page = $this->make_page([$field1, $field2, $other]);

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
        // Settings registered with a text_domain-prefixed page slug (e.g. 'my_plugin_general')
        // must still be saved when the active tab is 'general'.
        $prefixed = new WP_Setting('prefixed_field', 'Prefixed', 'text', 'my_plugin_general', 'general');
        $page = $this->make_page([$prefixed]);

        $_POST['my_plugin_prefixed_field'] = 'value';
        $page->expose_save_tab('general');
        unset($_POST['my_plugin_prefixed_field']);

        $this->assertSame('value', $this->getOption('my_plugin_prefixed_field'));
    }
}
