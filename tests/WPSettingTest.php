<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;

class Test_WP_Settings_Advanced_Fields extends WP_Settings
{
    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->sections = array(
            'main' => array(
                'name' => 'Main',
                'tab' => 'general',
                'callback' => '__return_false',
            ),
        );

        parent::__construct('my-plugin');
    }
}

/**
 * Tests for WP_Setting class
 */
class WPSettingTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set static text_domain for all tests
        WP_Setting::$text_domain = 'my-plugin';
    }

    // -------------------------------------------------------------------------
    // WP_Setting::make() named constructor
    // -------------------------------------------------------------------------

    public function test_make_returns_instance_with_correct_properties(): void
    {
        $setting = WP_Setting::make(
            name:          'make_option',
            title:         'Make Option',
            type:          'text',
            page:          'general',
            section:       'main',
            width:         '300px',
            description:   'A description',
            required:      true,
            default_value: 'default',
        );

        $this->assertInstanceOf(WP_Setting::class, $setting);
        $this->assertSame('make_option', $setting->name);
        $this->assertSame('my_plugin_make_option', $setting->slug);
        $this->assertSame('Make Option', $setting->title);
        $this->assertSame('text', $setting->type);
        $this->assertSame('general', $setting->page);
        $this->assertSame('main', $setting->section);
        $this->assertSame('300px', $setting->width);
        $this->assertSame('A description', $setting->description);
        $this->assertTrue($setting->required);
        $this->assertSame('default', $setting->default_value);
    }

    public function test_make_passes_args_through(): void
    {
        $setting = WP_Setting::make(
            name:    'select_option',
            title:   'Select Option',
            type:    'select',
            page:    'general',
            section: 'main',
            args:    ['options' => ['a' => 'A', 'b' => 'B']],
        );

        $this->assertSame(['a' => 'A', 'b' => 'B'], $setting->args['options']);
    }

    public function test_make_sanitize_callback_injected_into_args(): void
    {
        $cb = fn($v) => strtoupper($v);

        $setting = WP_Setting::make(
            name:              'cb_option',
            title:             'CB Option',
            type:              'text',
            page:              'general',
            section:           'main',
            sanitize_callback: $cb,
        );

        $this->assertSame($cb, $setting->args['sanitize_callback'] ?? null);
    }

    public function test_make_sanitize_callback_does_not_override_args_entry(): void
    {
        $from_args = fn($v) => strtolower($v);
        $from_param = fn($v) => strtoupper($v);

        $setting = WP_Setting::make(
            name:              'cb_option2',
            title:             'CB Option 2',
            type:              'text',
            page:              'general',
            section:           'main',
            args:              ['sanitize_callback' => $from_args],
            sanitize_callback: $from_param,
        );

        // args entry wins — param is ignored when args already has one
        $this->assertSame($from_args, $setting->args['sanitize_callback']);
    }

    /**
     * Test that constructor sets properties correctly
     */
    public function test_constructor_sets_properties(): void
    {
        $setting = new WP_Setting(
            'test_option',
            'Test Option',
            'text',
            'general',
            'main',
            '400px',
            'A test description',
            true,
            'default_value'
        );

        $this->assertSame('test_option', $setting->name);
        $this->assertSame('my_plugin_test_option', $setting->slug);
        $this->assertSame('Test Option', $setting->title);
        $this->assertSame('text', $setting->type);
        $this->assertSame('general', $setting->page);
        $this->assertSame('main', $setting->section);
        $this->assertSame('400px', $setting->width);
        $this->assertSame('A test description', $setting->description);
        $this->assertTrue($setting->required);
        $this->assertSame('default_value', $setting->default_value);
    }

    /**
     * Test that checkbox type sets default value to 'on' if truthy
     */
    public function test_checkbox_default_value_normalized(): void
    {
        $setting = new WP_Setting(
            'checkbox_option',
            'Checkbox',
            'checkbox',
            'general',
            'main',
            null,
            null,
            false,
            'yes'
        );

        $this->assertSame('on', $setting->default_value);
    }

    /**
     * Test that section with spaces is converted to underscore lowercase
     */
    public function test_section_slug_normalized(): void
    {
        $setting = new WP_Setting(
            'test_option',
            'Test',
            'text',
            'general',
            'My Section Name'
        );

        $this->assertSame('my_section_name', $setting->section);
    }

    /**
     * Test init registers setting with WordPress
     */
    public function test_init_registers_setting(): void
    {
        $setting = new WP_Setting(
            'test_option',
            'Test Option',
            'text',
            'general',
            'main'
        );

        $setting->init();

        // Check option was added
        $this->assertNotNull($this->getOption('my_plugin_test_option'));

        // Check settings field was registered
        $fields = $this->getRegisteredSettingsFields();
        $this->assertArrayHasKey('my_plugin_test_option_field', $fields);
    }

    /**
     * Test hidden field type does not add settings field
     */
    public function test_hidden_field_skips_settings_field(): void
    {
        $setting = new WP_Setting(
            'hidden_option',
            'Hidden',
            'hidden',
            'general',
            'main',
            null,
            null,
            false,
            'secret_value'
        );

        $setting->init();

        // Settings field should not be registered for hidden type
        $fields = $this->getRegisteredSettingsFields();
        $this->assertArrayNotHasKey('my_plugin_hidden_option_field', $fields);
    }

    /**
     * Test render_unbound uses provided name and value.
     */
    public function test_render_unbound_uses_overrides(): void
    {
        $setting = new WP_Setting(
            'test_option',
            'Test Option',
            'text',
            'general',
            'main'
        );

        ob_start();
        $setting->render_unbound('custom', 'custom_name', 'custom_id');
        $output = ob_get_clean();

        $this->assertStringContainsString('name="custom_name"', $output);
        $this->assertStringContainsString('id="custom_id"', $output);
        $this->assertStringContainsString('value="custom"', $output);
    }

    /**
     * Test sanitize_value applies sanitize callback.
     */
    public function test_sanitize_value_uses_callback(): void
    {
        $setting = new WP_Setting(
            'test_option',
            'Test Option',
            'text',
            'general',
            'main'
        );

        $this->assertSame('hello', $setting->sanitize_value('hello<script>'));
    }

    /**
     * Test advanced field type registers with empty title
     */
    public function test_advanced_field_registers_with_empty_title(): void
    {
        $child = new WP_Setting(
            'child_option',
            'Child',
            'text',
            'general',
            'main'
        );

        $setting = new WP_Setting(
            'advanced_option',
            'Advanced Settings',
            'advanced',
            'general',
            'main',
            null,
            'Advanced description',
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $setting->init();

        $fields = $this->getRegisteredSettingsFields();
        $this->assertArrayHasKey('my_plugin_advanced_option_field', $fields);
        // Title should be empty for advanced fields
        $this->assertSame('', $fields['my_plugin_advanced_option_field']['title']);
    }

    public function test_advanced_children_do_not_register_standalone_fields(): void
    {
        $child = new WP_Setting(
            'child_option',
            'Child',
            'text',
            'general',
            'main'
        );

        $setting = new WP_Setting(
            'advanced_option',
            'Advanced Settings',
            'advanced',
            'general',
            'main',
            null,
            'Advanced description',
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $setting->init();

        $fields = $this->getRegisteredSettingsFields();

        $this->assertArrayHasKey('my_plugin_advanced_option_field', $fields);
        $this->assertArrayNotHasKey('my_plugin_child_option_field', $fields);
        $this->assertNotNull($this->getOption('my_plugin_child_option'));
    }

    public function test_wp_settings_init_skips_advanced_child_field_registration(): void
    {
        $child = new WP_Setting(
            'child_option',
            'Child',
            'text',
            'general',
            'main'
        );

        $setting = new WP_Setting(
            'advanced_option',
            'Advanced Settings',
            'advanced',
            'general',
            'main',
            null,
            'Advanced description',
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $settings_page = new Test_WP_Settings_Advanced_Fields([$setting]);
        $settings_page->init();

        $fields = $this->getRegisteredSettingsFields();

        $this->assertArrayHasKey('my_plugin_advanced_option_field', $fields);
        $this->assertArrayNotHasKey('my_plugin_child_option_field', $fields);
        $this->assertNotNull($this->getOption('my_plugin_child_option'));
    }

    /**
     * Test children are extracted from args
     */
    public function test_children_extracted_from_args(): void
    {
        $child1 = new WP_Setting('my-plugin', 'child1', 'Child 1', 'text', 'general', 'main');
        $child2 = new WP_Setting('my-plugin', 'child2', 'Child 2', 'checkbox', 'general', 'main');

        $setting = new WP_Setting(
            'parent',
            'Parent',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child1, $child2]]
        );

        $this->assertCount(2, $setting->children);
        $this->assertSame($child1, $setting->children[0]);
        $this->assertSame($child2, $setting->children[1]);
    }

    /**
     * Test static get method retrieves option value
     */
    public function test_static_get_retrieves_option(): void
    {
        // Create a setting first to set the text_domain
        new WP_Setting('my-plugin', 'dummy', 'Dummy', 'text', 'general', 'main');

        $this->setOption('my_plugin_my_option', 'test_value');

        $value = WP_Setting::get('my_option');
        $this->assertSame('test_value', $value);
    }

    /**
     * Test static get with default value
     */
    public function test_static_get_returns_default(): void
    {
        // Create a setting first to set the text_domain
        new WP_Setting('my-plugin', 'dummy', 'Dummy', 'text', 'general', 'main');

        $value = WP_Setting::get('nonexistent_option', 'default');
        $this->assertSame('default', $value);
    }

    /**
     * Test static set method updates option value
     */
    public function test_static_set_updates_option(): void
    {
        // Create a setting first to set the text_domain
        new WP_Setting('my-plugin', 'dummy', 'Dummy', 'text', 'general', 'main');

        WP_Setting::set('my_option', 'new_value');

        $this->assertSame('new_value', $this->getOption('my_plugin_my_option'));
    }

    /**
     * WP_Setting::set_text_domain() normalizes hyphens to underscores, matching
     * WP_Settings::__construct()'s normalization, and makes get()/set() usable
     * without constructing a WP_Settings subclass.
     */
    public function test_set_text_domain_normalizes_and_enables_prefixing(): void
    {
        WP_Setting::$text_domain = null;

        WP_Setting::set_text_domain('my-plugin');

        $this->assertSame('my_plugin', WP_Setting::$text_domain);

        WP_Setting::set('some_setting', 'a_value');
        $this->assertSame('a_value', $this->getOption('my_plugin_some_setting'));
    }

    /**
     * WP_Setting::get() should warn (via _doing_it_wrong) when $text_domain is unset,
     * while still returning the caller's default value unchanged.
     */
    public function test_get_triggers_doing_it_wrong_when_text_domain_unset(): void
    {
        WP_Setting::$text_domain = null;

        $value = WP_Setting::get('some_setting', 'default_value');

        $this->assertSame('default_value', $value);
        $calls = $this->getDoingItWrongCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('WP_Setting::get', $calls[0]['function_name']);
    }

    /**
     * WP_Setting::set() should warn (via _doing_it_wrong) when $text_domain is unset,
     * while still performing the (unprefixed) write unchanged.
     */
    public function test_set_triggers_doing_it_wrong_when_text_domain_unset(): void
    {
        WP_Setting::$text_domain = null;

        $result = WP_Setting::set('some_setting', 'a_value');

        $this->assertTrue($result);
        $this->assertSame('a_value', $this->getOption('some_setting'));
        $calls = $this->getDoingItWrongCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('WP_Setting::set', $calls[0]['function_name']);
    }

    /**
     * No notice once WP_Setting::set_text_domain() has established the domain.
     */
    public function test_get_and_set_do_not_trigger_doing_it_wrong_once_domain_set_via_setter(): void
    {
        WP_Setting::set_text_domain('my-plugin');

        WP_Setting::get('some_setting', 'default_value');
        WP_Setting::set('some_setting', 'a_value');

        $this->assertSame([], $this->getDoingItWrongCalls());
    }

    /**
     * No notice once a WP_Settings subclass construction has established the domain
     * (the original, already-working mechanism).
     */
    public function test_get_and_set_do_not_trigger_doing_it_wrong_once_domain_set_via_construction(): void
    {
        new WP_Setting('dummy', 'Dummy', 'text', 'general', 'main');

        WP_Setting::get('some_setting', 'default_value');
        WP_Setting::set('some_setting', 'a_value');

        $this->assertSame([], $this->getDoingItWrongCalls());
    }

    /**
     * Test static get/set/delete tolerate a null setting name without triggering
     * a str_replace()/strpos() null-subject deprecation warning on PHP 8.1+.
     */
    public function test_static_methods_handle_null_setting_without_deprecation(): void
    {
        // Create a setting first to set the text_domain
        new WP_Setting('my-plugin', 'dummy', 'Dummy', 'text', 'general', 'main');

        $this->assertFalse(WP_Setting::get(null));
        $this->assertIsBool(WP_Setting::set(null, 'value'));
        $this->assertIsBool(WP_Setting::delete(null));
    }

    /**
     * Regression test locking in the str_replace()/strpos() null-subject fix already
     * present on `main` (WP_Setting::set()/delete() cast the setting name to string
     * before use). Exercises the text-domain-normalization branch specifically, since
     * that's the code path that calls str_replace() when the registered text domain
     * contains a hyphen — the same class of bug reported (and previously fixed) against
     * `str_replace()` in this file at v2.27.3.
     */
    public function test_set_and_delete_normalize_hyphenated_domain_without_deprecation(): void
    {
        WP_Setting::$text_domain = 'my-plugin';

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_DEPRECATED | E_WARNING);

        $stored_value = null;
        $value_after_delete = null;

        try {
            // Setting name already carrying the hyphenated prefix triggers the
            // str_replace('my-plugin_', 'my_plugin_', $setting) normalization.
            WP_Setting::set('my-plugin_hyphen_option', 'value-one');
            $stored_value = $this->getOption('my_plugin_hyphen_option');

            WP_Setting::delete('my-plugin_hyphen_option');
            $value_after_delete = $this->getOption('my_plugin_hyphen_option', 'not-found');

            // A null setting name (the historical trigger for the str_replace()/strpos()
            // deprecation) must also flow through the same normalization safely.
            WP_Setting::set(null, 'value-two');
            WP_Setting::delete(null);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings,
            'Normalizing a hyphenated text domain must not raise a str_replace()/strpos() deprecation notice.');
        $this->assertSame('value-one', $stored_value,
            'The hyphenated prefix must be normalized to underscores when storing the option.');
        $this->assertSame('not-found', $value_after_delete,
            'The normalized option key must be deleted.');
    }

    /**
     * Test init_type renders text input
     */
    public function test_init_type_renders_text_input(): void
    {
        $setting = new WP_Setting(
            'text_option',
            'Text Option',
            'text',
            'general',
            'main',
            '300px',
            'Enter text here'
        );

        $this->setOption('my_plugin_text_option', 'current_value');

        ob_start();
        $setting->init_type();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="my_plugin_text_option"', $output);
        $this->assertStringContainsString('value="current_value"', $output);
        $this->assertStringContainsString('style="width:300px;"', $output);
        $this->assertStringContainsString('Enter text here', $output);
    }

    /**
     * Test init_checkbox renders checkbox input
     */
    public function test_init_checkbox_renders_checkbox(): void
    {
        $setting = new WP_Setting(
            'checkbox_option',
            'Checkbox Option',
            'checkbox',
            'general',
            'main',
            null,
            'Enable this feature'
        );

        $this->setOption('my_plugin_checkbox_option', true);

        ob_start();
        $setting->init_checkbox();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="my_plugin_checkbox_option"', $output);
        $this->assertStringContainsString('checked="checked"', $output);
        $this->assertStringContainsString('Enable this feature', $output);
    }

    /**
     * Test init_textarea renders textarea
     */
    public function test_init_textarea_renders_textarea(): void
    {
        $setting = new WP_Setting(
            'textarea_option',
            'Textarea Option',
            'textarea',
            'general',
            'main',
            '500px',
            'Enter long text'
        );

        $this->setOption('my_plugin_textarea_option', 'multiline content');

        ob_start();
        $setting->init_textarea();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="my_plugin_textarea_option"', $output);
        $this->assertStringContainsString('multiline content', $output);
        $this->assertStringContainsString('style="width:500px;"', $output);
    }

    /**
     * Test init_select renders select dropdown
     */
    public function test_init_select_renders_select(): void
    {
        $setting = new WP_Setting(
            'select_option',
            'Select Option',
            'select',
            'general',
            'main',
            null,
            'Choose an option',
            false,
            'option2',
            null,
            ['options' => ['option1' => 'Option 1', 'option2' => 'Option 2', 'option3' => 'Option 3']]
        );

        $this->setOption('my_plugin_select_option', 'option2');

        ob_start();
        $setting->init_select();
        $output = ob_get_clean();

        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('name="my_plugin_select_option"', $output);
        $this->assertStringContainsString('Option 1', $output);
        $this->assertStringContainsString('Option 2', $output);
        $this->assertStringContainsString('Option 3', $output);
    }

    /**
     * Test init_type renders sortable list in saved order
     */
    public function test_sortable_renders_list_in_saved_order(): void
    {
        $setting = new WP_Setting(
            'sortable_option',
            'Sortable Option',
            'sortable',
            'general',
            'main',
            null,
            'Sort items',
            false,
            null,
            null,
            ['options' => ['item_a' => 'Item A', 'item_b' => 'Item B', 'item_c' => 'Item C']]
        );

        $this->setOption('my_plugin_sortable_option', ['item_b', 'item_a']);

        ob_start();
        $setting->init_type();
        $output = ob_get_clean();

        $this->assertStringContainsString('class="wps-sortable-list"', $output);
        $this->assertStringContainsString('name="my_plugin_sortable_option[]"', $output);

        $pos_b = strpos($output, 'data-key="item_b"');
        $pos_a = strpos($output, 'data-key="item_a"');
        $this->assertNotFalse($pos_b);
        $this->assertNotFalse($pos_a);
        $this->assertLessThan($pos_a, $pos_b);
    }

    /**
     * Test sortable supports callable options
     */
    public function test_sortable_supports_callable_options(): void
    {
        $setting = new WP_Setting(
            'sortable_callable',
            'Sortable Callable',
            'sortable',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'options' => function() {
                    return ['item_a' => 'Item A', 'item_b' => 'Item B'];
                }
            ]
        );

        ob_start();
        $setting->init_type();
        $output = ob_get_clean();

        $this->assertStringContainsString('Item A', $output);
        $this->assertStringContainsString('Item B', $output);
    }

    /**
     * Test init_hidden renders hidden input
     */
    public function test_init_hidden_renders_hidden_input(): void
    {
        $setting = new WP_Setting(
            'hidden_option',
            '',
            'hidden',
            'general',
            'main',
            null,
            null,
            false,
            'secret'
        );

        $this->setOption('my_plugin_hidden_option', 'hidden_value');

        ob_start();
        $setting->init_hidden();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="hidden"', $output);
        $this->assertStringContainsString('name="my_plugin_hidden_option"', $output);
        $this->assertStringContainsString('value="hidden_value"', $output);
    }

    /**
     * Test init_hidden handles array value safely
     */
    public function test_init_hidden_handles_array_value(): void
    {
        $setting = new WP_Setting(
            'hidden_option',
            '',
            'hidden',
            'general',
            'main',
            null,
            null,
            false,
            'fallback'
        );

        // Set an array value (edge case)
        $this->setOption('my_plugin_hidden_option', ['unexpected', 'array']);

        ob_start();
        $setting->init_hidden();
        $output = ob_get_clean();

        // Should fall back to default value when array is detected
        $this->assertStringContainsString('value="fallback"', $output);
    }

    /**
     * Test init_advanced renders collapsible details section
     */
    public function test_init_advanced_renders_details_section(): void
    {
        $child = new WP_Setting(
            'child_checkbox',
            'Child Checkbox',
            'checkbox',
            'general',
            'main',
            null,
            'Child description'
        );

        $setting = new WP_Setting(
            'advanced_settings',
            'Advanced Settings',
            'advanced',
            'general',
            'main',
            null,
            'Configure advanced options',
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('<details', $output);
        $this->assertStringContainsString('<summary', $output);
        $this->assertStringContainsString('Advanced Settings', $output);
        $this->assertStringContainsString('Configure advanced options', $output);
        $this->assertStringContainsString('Child Checkbox', $output);
    }

    public function test_init_advanced_text_child_renders_input(): void
    {
        $child = new WP_Setting(
            'child_text',
            'Child Text',
            'text',
            'general',
            'main'
        );

        $setting = new WP_Setting(
            'advanced_settings',
            'Advanced Settings',
            'advanced',
            'general',
            'main',
            null,
            'Configure advanced options',
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        // The text child now renders through the shared field renderer
        // (init_type → render_unbound), same as a top-level text field.
        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="my_plugin_child_text"', $output);
        $this->assertStringContainsString('Child Text', $output);
        // Enter-submit is preserved via the shared renderer.
        $this->assertStringContainsString("event.key==='Enter'", $output);
        $this->assertStringContainsString('requestSubmit', $output);
    }

    /**
     * Test required attribute is added to inputs
     */
    public function test_required_attribute_added(): void
    {
        $setting = new WP_Setting(
            'required_option',
            'Required Option',
            'text',
            'general',
            'main',
            null,
            null,
            true  // required
        );

        ob_start();
        $setting->init_type();
        $output = ob_get_clean();

        $this->assertStringContainsString('required', $output);
    }

    /**
     * Test is_valid_url validates URLs correctly
     */
    public function test_is_valid_url(): void
    {
        // Valid URLs
        $this->assertTrue(WP_Setting::is_valid_url('https://example.com'));
        $this->assertTrue(WP_Setting::is_valid_url('http://example.com/path'));
        $this->assertTrue(WP_Setting::is_valid_url('https://example.com:8080/path?query=value'));
        $this->assertTrue(WP_Setting::is_valid_url('ftp://files.example.com'));

        // Invalid URLs
        $this->assertFalse(WP_Setting::is_valid_url(''));
        $this->assertFalse(WP_Setting::is_valid_url('not a url'));
        $this->assertFalse(WP_Setting::is_valid_url('example.com')); // missing scheme
        $this->assertFalse(WP_Setting::is_valid_url('javascript:alert(1)'));
    }

    /**
     * Test is_valid_email validates emails correctly
     */
    public function test_is_valid_email(): void
    {
        // Valid emails
        $this->assertTrue(WP_Setting::is_valid_email('user@example.com'));
        $this->assertTrue(WP_Setting::is_valid_email('test.user+tag@example.co.uk'));
        $this->assertTrue(WP_Setting::is_valid_email('user123@sub.example.com'));

        // Invalid emails
        $this->assertFalse(WP_Setting::is_valid_email(''));
        $this->assertFalse(WP_Setting::is_valid_email('not an email'));
        $this->assertFalse(WP_Setting::is_valid_email('@example.com'));
        $this->assertFalse(WP_Setting::is_valid_email('user@'));
        $this->assertFalse(WP_Setting::is_valid_email('user example.com'));
    }

    /**
     * Test is_not_empty validates non-empty values
     */
    public function test_is_not_empty(): void
    {
        // Non-empty values
        $this->assertTrue(WP_Setting::is_not_empty('text'));
        $this->assertTrue(WP_Setting::is_not_empty('0'));
        $this->assertTrue(WP_Setting::is_not_empty(['item']));
        $this->assertTrue(WP_Setting::is_not_empty(123));

        // Empty values
        $this->assertFalse(WP_Setting::is_not_empty(''));
        $this->assertFalse(WP_Setting::is_not_empty('   '));  // whitespace only
        $this->assertFalse(WP_Setting::is_not_empty(null));
        $this->assertFalse(WP_Setting::is_not_empty([]));
        $this->assertFalse(WP_Setting::is_not_empty(0)); // Note: 0 is considered empty by PHP's empty()
    }

    /**
     * Test sanitize_url sanitizes and validates URLs
     */
    public function test_sanitize_url(): void
    {
        // Valid URL
        $result = WP_Setting::sanitize_url('https://example.com/path');
        $this->assertSame('https://example.com/path', $result);

        // Empty value
        $result = WP_Setting::sanitize_url('');
        $this->assertSame('', $result);

        // Invalid URL
        $result = WP_Setting::sanitize_url('not a url');
        $this->assertFalse($result);
    }

    /**
     * Test sanitize_email sanitizes and validates emails
     */
    public function test_sanitize_email(): void
    {
        // Valid email
        $result = WP_Setting::sanitize_email('user@example.com');
        $this->assertSame('user@example.com', $result);

        // Email with extra whitespace (should be trimmed by sanitize_email)
        $result = WP_Setting::sanitize_email(' user@example.com ');
        $this->assertSame('user@example.com', $result);

        // Empty value
        $result = WP_Setting::sanitize_email('');
        $this->assertSame('', $result);

        // Invalid email
        $result = WP_Setting::sanitize_email('not an email');
        $this->assertFalse($result);
    }

    /**
     * Test sanitize_text sanitizes text input
     */
    public function test_sanitize_text(): void
    {
        // Regular text
        $result = WP_Setting::sanitize_text('Hello World');
        $this->assertSame('Hello World', $result);

        // Text with extra whitespace
        $result = WP_Setting::sanitize_text('  trimmed  ');
        $this->assertSame('trimmed', $result);

        // Text with newlines (should be removed by sanitize_text_field)
        $result = WP_Setting::sanitize_text("Line 1\nLine 2");
        $this->assertSame('Line 1 Line 2', $result);
    }

    /**
     * Test email field type gets default sanitize_callback
     */
    public function test_email_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'email_field',
            'Email',
            'email',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving an email
        $_POST['my_plugin_email_field'] = ' user@example.com ';
        $setting->save();

        // Check the value was sanitized
        $value = WP_Setting::get('email_field');
        $this->assertSame('user@example.com', $value);

        // Clean up
        unset($_POST['my_plugin_email_field']);
    }

    /**
     * Test email field rejects invalid emails
     */
    public function test_email_field_rejects_invalid_email(): void
    {
        $setting = new WP_Setting(
            'email_field',
            'Email',
            'email',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving an invalid email
        $_POST['my_plugin_email_field'] = 'not an email';
        $setting->save();

        // Check the value was rejected (should return false)
        $value = WP_Setting::get('email_field');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my_plugin_email_field']);
    }

    /**
     * Test url field type gets default sanitize_callback
     */
    public function test_url_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'url_field',
            'URL',
            'url',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving a URL
        $_POST['my_plugin_url_field'] = 'https://example.com/path';
        $setting->save();

        // Check the value was sanitized
        $value = WP_Setting::get('url_field');
        $this->assertSame('https://example.com/path', $value);

        // Clean up
        unset($_POST['my_plugin_url_field']);
    }

    /**
     * Test url field rejects invalid URLs
     */
    public function test_url_field_rejects_invalid_url(): void
    {
        $setting = new WP_Setting(
            'url_field',
            'URL',
            'url',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving an invalid URL
        $_POST['my_plugin_url_field'] = 'not a url';
        $setting->save();

        // Check the value was rejected
        $value = WP_Setting::get('url_field');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my_plugin_url_field']);
    }

    /**
     * Test sanitize_color sanitizes and validates hex colors
     */
    public function test_sanitize_color(): void
    {
        // Six-digit hex, which is all <input type="color"> ever submits
        $this->assertSame('#e27728', WP_Setting::sanitize_color('#e27728'));

        // Three-digit shorthand and uppercase digits are valid
        $this->assertSame('#FFF', WP_Setting::sanitize_color('#FFF'));

        // Surrounding whitespace is trimmed
        $this->assertSame('#e27728', WP_Setting::sanitize_color('  #e27728  '));

        // No value submitted
        $this->assertSame('', WP_Setting::sanitize_color(''));
        $this->assertSame('', WP_Setting::sanitize_color(null));
        $this->assertSame('', WP_Setting::sanitize_color('   '));

        // Anything else is rejected outright rather than partially cleaned
        $this->assertFalse(WP_Setting::sanitize_color('e27728'));
        $this->assertFalse(WP_Setting::sanitize_color('#e2772'));
        $this->assertFalse(WP_Setting::sanitize_color('#gggggg'));
        $this->assertFalse(WP_Setting::sanitize_color('rgb(226, 119, 40)'));
        $this->assertFalse(WP_Setting::sanitize_color('red'));
        $this->assertFalse(WP_Setting::sanitize_color(array('#e27728')));
    }

    /**
     * Test color field type gets default sanitize_callback
     */
    public function test_color_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'color_field',
            'Color',
            'color',
            'general',
            'main'
        );

        $setting->init();

        $_POST['my_plugin_color_field'] = '#e27728';
        $setting->save();

        $this->assertSame('#e27728', WP_Setting::get('color_field'));

        // Clean up
        unset($_POST['my_plugin_color_field']);
    }

    /**
     * A color field must not store whatever was posted (issue #10): the browser
     * only submits #rrggbb, but options.php writes every registered option from
     * $_POST, and consumers interpolate colors into CSS.
     */
    public function test_color_field_rejects_invalid_color(): void
    {
        $setting = new WP_Setting(
            'color_field',
            'Color',
            'color',
            'general',
            'main'
        );

        $setting->init();

        $_POST['my_plugin_color_field'] = '#e27728<script>alert(1)</script>';
        $setting->save();

        $this->assertFalse(WP_Setting::get('color_field'));

        // Clean up
        unset($_POST['my_plugin_color_field']);
    }

    /**
     * Test a custom sanitize_callback still wins for color fields
     */
    public function test_color_field_custom_sanitize_callback_overrides_default(): void
    {
        $setting = new WP_Setting(
            'color_field',
            'Color',
            'color',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            array(
                'sanitize_callback' => function ($value) {
                    return 'rebeccapurple';
                },
            )
        );

        $setting->init();

        $_POST['my_plugin_color_field'] = 'red';
        $setting->save();

        $this->assertSame('rebeccapurple', WP_Setting::get('color_field'));

        // Clean up
        unset($_POST['my_plugin_color_field']);
    }

    /**
     * Repeater children of type color get the same validation, dropping an
     * invalid value instead of storing a tag-stripped remnant of it.
     */
    public function test_repeater_sanitizes_color_children(): void
    {
        $setting = new WP_Setting(
            'r_color',
            'r',
            'repeater',
            'tab',
            'sec',
            null,
            null,
            false,
            array(),
            null,
            array(
                'children' => array(
                    array('name' => 'label', 'type' => 'text'),
                    array('name' => 'swatch', 'type' => 'color'),
                ),
            )
        );

        $out = $setting->sanitize_repeater(array(
            array('label' => 'Marker', 'swatch' => '#e27728'),
            array('label' => 'Bad', 'swatch' => '#e27728<script>alert(1)</script>'),
        ));

        $this->assertSame('#e27728', $out[0]['swatch']);
        $this->assertSame('', $out[1]['swatch']);
    }

    /**
     * Test number field type gets default sanitize_callback
     */
    public function test_number_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'number_field',
            'Number',
            'number',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving a number
        $_POST['my_plugin_number_field'] = '42';
        $setting->save();

        // Check the value was saved
        $value = WP_Setting::get('number_field');
        $this->assertSame('42', $value);

        // Clean up
        unset($_POST['my_plugin_number_field']);
    }

    /**
     * Test number field rejects non-numeric values
     */
    public function test_number_field_rejects_non_numeric(): void
    {
        $setting = new WP_Setting(
            'number_field',
            'Number',
            'number',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving a non-numeric value
        $_POST['my_plugin_number_field'] = 'not a number';
        $setting->save();

        // Check the value was rejected (empty string)
        $value = WP_Setting::get('number_field');
        $this->assertSame('', $value);

        // Clean up
        unset($_POST['my_plugin_number_field']);
    }

    /**
     * Test sortable field type gets default sanitize_callback
     */
    public function test_sortable_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'sortable_field',
            'Sortable',
            'sortable',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['item_a' => 'Item A', 'item_b' => 'Item B', 'item_c' => 'Item C']]
        );

        $setting->init();

        $_POST['my_plugin_sortable_field'] = ['item_b', 'item_b', 'invalid', 'item_a'];
        $setting->save();

        $value = WP_Setting::get('sortable_field');
        $this->assertSame(['item_b', 'item_a', 'item_c'], $value);

        unset($_POST['my_plugin_sortable_field']);
    }

    /**
     * Test text field type gets default sanitize_callback
     */
    public function test_text_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'text_field',
            'Text',
            'text',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving text with HTML
        $_POST['my_plugin_text_field'] = '<b>Bold</b> text';
        $setting->save();

        // Check the value was sanitized (tags removed but content kept)
        $value = WP_Setting::get('text_field');
        $this->assertSame('Bold text', $value);

        // Clean up
        unset($_POST['my_plugin_text_field']);
    }

    /**
     * Test textarea field type gets default sanitize_callback
     */
    public function test_textarea_field_has_default_sanitization(): void
    {
        $setting = new WP_Setting(
            'textarea_field',
            'Textarea',
            'textarea',
            'general',
            'main'
        );

        $setting->init();

        // Simulate saving text with HTML
        $_POST['my_plugin_textarea_field'] = '<b>Bold text</b>';
        $setting->save();

        // Check the value was sanitized (tags removed)
        $value = WP_Setting::get('textarea_field');
        $this->assertSame('Bold text', $value);

        // Clean up
        unset($_POST['my_plugin_textarea_field']);
    }

    /**
     * Test textarea field preserves newlines while removing tags
     */
    public function test_textarea_field_preserves_newlines(): void
    {
        $setting = new WP_Setting(
            'textarea_newlines',
            'Textarea with Newlines',
            'textarea',
            'general',
            'main'
        );

        $setting->init();

        // Test that newlines are preserved and HTML tags are removed
        $_POST['my_plugin_textarea_newlines'] = "Line 1\nLine 2\n<b>Bold Line 3</b>";
        $setting->save();

        $value = WP_Setting::get('textarea_newlines');
        // Should preserve newlines but remove HTML tags
        $this->assertSame("Line 1\nLine 2\nBold Line 3", $value);

        // Clean up
        unset($_POST['my_plugin_textarea_newlines']);
    }

    /**
     * Test custom sanitize_callback overrides default
     */
    public function test_custom_sanitize_callback_overrides_default(): void
    {
        $setting = new WP_Setting(
            'custom_email',
            'Custom Email',
            'email',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'sanitize_callback' => function($value) {
                    return strtoupper($value);
                }
            ]
        );

        $setting->init();

        // Simulate saving an email
        $_POST['my_plugin_custom_email'] = 'user@example.com';
        $setting->save();

        // Check the custom callback was used (uppercase)
        $value = WP_Setting::get('custom_email');
        $this->assertSame('USER@EXAMPLE.COM', $value);

        // Clean up
        unset($_POST['my_plugin_custom_email']);
    }

    /**
     * Test custom sanitize_callback works on field type without default
     */
    public function test_custom_sanitize_callback_on_field_without_default(): void
    {
        $setting = new WP_Setting(
            'custom_select',
            'Custom Select',
            'select',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'options' => ['a' => 'Option A', 'b' => 'Option B'],
                'sanitize_callback' => function($value) {
                    return 'sanitized_' . $value;
                }
            ]
        );

        $setting->init();

        // Simulate saving a select value
        $_POST['my_plugin_custom_select'] = 'a';
        $setting->save();

        // Check the custom callback was applied
        $value = WP_Setting::get('custom_select');
        $this->assertSame('sanitized_a', $value);

        // Clean up
        unset($_POST['my_plugin_custom_select']);
    }

    /**
     * Test custom sanitize_callback with static method
     */
    public function test_custom_sanitize_callback_with_static_method(): void
    {
        $setting = new WP_Setting(
            'validated_email',
            'Validated Email',
            'text', // Use text type with email validation callback
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'sanitize_callback' => [WP_Setting::class, 'sanitize_email']
            ]
        );

        $setting->init();

        // Simulate saving a valid email
        $_POST['my_plugin_validated_email'] = ' test@example.com ';
        $setting->save();

        // Check the static method was called and email was sanitized
        $value = WP_Setting::get('validated_email');
        $this->assertSame('test@example.com', $value);

        // Clean up
        unset($_POST['my_plugin_validated_email']);
    }

    /**
     * Test custom sanitize_callback that returns false
     */
    public function test_custom_sanitize_callback_returns_false(): void
    {
        $setting = new WP_Setting(
            'validated_number',
            'Validated Number',
            'number',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'sanitize_callback' => function($value) {
                    // Reject values over 100
                    return (is_numeric($value) && $value <= 100) ? $value : false;
                }
            ]
        );

        $setting->init();

        // Test valid value
        $_POST['my_plugin_validated_number'] = '50';
        $setting->save();
        $value = WP_Setting::get('validated_number');
        $this->assertSame('50', $value);

        // Test invalid value (over 100)
        $_POST['my_plugin_validated_number'] = '150';
        $setting->save();
        $value = WP_Setting::get('validated_number');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my_plugin_validated_number']);
    }

    /**
     * Test custom sanitize_callback receives correct value
     */
    public function test_custom_sanitize_callback_receives_correct_value(): void
    {
        $received_value = null;

        $setting = new WP_Setting(
            'callback_test',
            'Callback Test',
            'text',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'sanitize_callback' => function($value) use (&$received_value) {
                    $received_value = $value;
                    return $value;
                }
            ]
        );

        $setting->init();

        // Simulate saving
        $_POST['my_plugin_callback_test'] = 'test_value_123';
        $setting->save();

        // Check the callback received the correct value
        $this->assertSame('test_value_123', $received_value);

        // Clean up
        unset($_POST['my_plugin_callback_test']);
    }

    // -------------------------------------------------------------------------
    // Fix 1: fieldset children saving
    // -------------------------------------------------------------------------

    public function test_fieldset_saves_checkbox_child(): void
    {
        $child = new WP_Setting('child_check', 'Child Check', 'checkbox', 'general', 'main');

        $setting = new WP_Setting(
            'fieldset_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $_POST['my_plugin_child_check'] = 'on';
        $setting->save();
        unset($_POST['my_plugin_child_check']);

        $this->assertSame('1', $this->getOption('my_plugin_child_check'));
    }

    public function test_fieldset_saves_textarea_child(): void
    {
        $child = new WP_Setting('child_text', 'Child Text', 'textarea', 'general', 'main');

        $setting = new WP_Setting(
            'fieldset_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $_POST['my_plugin_child_text'] = 'hello world';
        $setting->save();
        unset($_POST['my_plugin_child_text']);

        $this->assertSame('hello world', $this->getOption('my_plugin_child_text'));
    }

    public function test_fieldset_parent_slug_not_written(): void
    {
        $child = new WP_Setting('child_text', 'Child Text', 'text', 'general', 'main');

        $setting = new WP_Setting(
            'fieldset_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $_POST['my_plugin_child_text'] = 'value';
        $setting->save();
        unset($_POST['my_plugin_child_text']);

        $this->assertFalse($this->getOption('my_plugin_fieldset_parent', false));
    }

    // -------------------------------------------------------------------------
    // Feature: containers delegate rendering to each child's own renderer,
    // so any field type (repeater, field_map, radio, richtext, nested
    // advanced/fieldset) works as a child — not just the old hardcoded set.
    // -------------------------------------------------------------------------

    /** Build an advanced container around one child. */
    private function makeAdvancedWith(WP_Setting $child): WP_Setting
    {
        return new WP_Setting(
            'adv_parent',
            'Advanced',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );
    }

    public function test_advanced_renders_repeater_child(): void
    {
        $child = new WP_Setting(
            'rep_child',
            'Repeater Child',
            'repeater',
            'general',
            'main',
            null,
            'Repeater description',
            false,
            null,
            null,
            ['children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']]]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('wps-repeater', $output);
        $this->assertStringContainsString('Add Row', $output);
        $this->assertStringContainsString('Repeater Child', $output);        // heading
        $this->assertStringContainsString('Repeater description', $output);   // child description
    }

    public function test_fieldset_renders_repeater_child(): void
    {
        $child = new WP_Setting(
            'rep_child_fs',
            'Repeater Child',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']]]
        );

        $setting = new WP_Setting(
            'fs_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('wps-repeater', $output);
        $this->assertStringContainsString('Add Row', $output);
    }

    public function test_advanced_renders_field_map_child(): void
    {
        $child = new WP_Setting(
            'map_child',
            'Map Child',
            'field_map',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['src' => 'Source']]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('wps-field-map-data', $output);
        $this->assertStringContainsString('Map Child', $output);
    }

    public function test_advanced_renders_radio_child(): void
    {
        $child = new WP_Setting(
            'radio_child',
            'Radio Child',
            'radio',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['a' => 'Alpha', 'b' => 'Beta']]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        // radio children previously produced no output at all
        $this->assertStringContainsString('type="radio"', $output);
        $this->assertStringContainsString('name="my_plugin_radio_child"', $output);
    }

    public function test_advanced_renders_richtext_child(): void
    {
        $child = new WP_Setting(
            'rich_child',
            'Rich Child',
            'richtext',
            'general',
            'main'
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        // richtext children previously produced no output at all
        $this->assertStringContainsString('wp-editor-area', $output);
        $this->assertStringContainsString('my_plugin_rich_child', $output);
    }

    public function test_advanced_renders_nested_advanced_child(): void
    {
        $grandchild = new WP_Setting('gc_text', 'Grandchild Text', 'text', 'general', 'main');

        $child = new WP_Setting(
            'nested_adv',
            'Nested Advanced',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$grandchild]]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        // Nested container and its own child both render.
        $this->assertStringContainsString('Nested Advanced', $output);
        $this->assertStringContainsString('name="my_plugin_gc_text"', $output);
    }

    public function test_advanced_unchecked_checkbox_child_submits_zero(): void
    {
        $child = new WP_Setting('cb_child', 'Checkbox Child', 'checkbox', 'general', 'main');

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        // Hidden companion input guarantees an unchecked box still submits 0.
        $this->assertStringContainsString('<input type="hidden" name="my_plugin_cb_child" value="0">', $output);
    }

    public function test_checkbox_save_stores_string_values(): void
    {
        $setting = new WP_Setting('cb_str', 'Checkbox', 'checkbox', 'general', 'main');

        $_POST['my_plugin_cb_str'] = 'on';
        $setting->save();
        $this->assertSame('1', $this->getOption('my_plugin_cb_str'));

        // The hidden companion input submits '0' when the box is unchecked.
        $_POST['my_plugin_cb_str'] = '0';
        $setting->save();
        $this->assertSame('0', $this->getOption('my_plugin_cb_str'));

        unset($_POST['my_plugin_cb_str']);
    }

    public function test_checkbox_saved_off_survives_reinit(): void
    {
        // A checkbox defaulting on must stay off after save(): the stored '0'
        // row keeps add_setting()'s add_option() from re-seeding the default.
        $setting = new WP_Setting(
            'cb_default_on',
            'Checkbox',
            'checkbox',
            'general',
            'main',
            null,
            null,
            false,
            'on'
        );

        $_POST['my_plugin_cb_default_on'] = '0';
        $setting->save();
        unset($_POST['my_plugin_cb_default_on']);

        $setting->init();

        $this->assertSame('0', $this->getOption('my_plugin_cb_default_on'));

        ob_start();
        $setting->init_checkbox();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('checked=', $output);
    }

    public function test_advanced_child_custom_callback_receives_args(): void
    {
        // A custom callback that *requires* $args — mirrors WP's field-callback
        // contract. Before the fix this threw ArgumentCountError (WSOD).
        $child = new WP_Setting(
            'cb_child_adv',
            'Custom CB Child',
            'text',
            'general',
            'main',
            null,
            null,
            false,
            null,
            function (array $args): void {
                echo '<span class="custom-cb">' . \esc_html($args['marker'] ?? '') . '</span>';
            },
            ['marker' => 'ADV_CUSTOM_OK']
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('ADV_CUSTOM_OK', $output);
    }

    public function test_fieldset_child_custom_callback_receives_args(): void
    {
        $child = new WP_Setting(
            'cb_child_fs',
            'Custom CB Child',
            'text',
            'general',
            'main',
            null,
            null,
            false,
            null,
            function (array $args): void {
                echo '<span class="custom-cb">' . \esc_html($args['marker'] ?? '') . '</span>';
            },
            ['marker' => 'FS_CUSTOM_OK']
        );

        $setting = new WP_Setting(
            'fs_cb_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('FS_CUSTOM_OK', $output);
    }

    public function test_top_level_advanced_spans_full_width(): void
    {
        $child = new WP_Setting('fw_adv_child', 'Child', 'text', 'general', 'main');
        $setting = $this->makeAdvancedWith($child);
        $setting->init(); // registers as a settings row -> full-width fix applies

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('colspan', $output);
        $this->assertStringContainsString('document.currentScript', $output);
    }

    public function test_top_level_fieldset_spans_full_width(): void
    {
        $child = new WP_Setting('fw_fs_child', 'Child', 'text', 'general', 'main');
        $setting = new WP_Setting(
            'fw_fs_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );
        $setting->init();

        ob_start();
        $setting->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('colspan', $output);
    }

    public function test_fieldset_hide_child_labels_omits_th_label(): void
    {
        $child = new WP_Setting('hcl_child', 'Duplicate Label', 'text', 'general', 'main');
        $setting = new WP_Setting(
            'hcl_parent',
            'Group Legend',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child], 'hide_child_labels' => true]
        );

        ob_start();
        $setting->init_fieldset();
        $output = ob_get_clean();

        // Visible heading suppressed; the title survives only as the control's
        // accessible name (issue #14).
        $this->assertStringContainsString('Group Legend', $output);
        $this->assertStringContainsString('colspan="2"', $output);
        $this->assertStringNotContainsString('<th scope="row"', $output);
        $this->assertStringContainsString(
            '<label class="screen-reader-text" for="my_plugin_hcl_child">Duplicate Label</label>',
            $output
        );
    }

    public function test_fieldset_keeps_child_labels_by_default(): void
    {
        $child = new WP_Setting('kcl_child', 'Kept Label', 'text', 'general', 'main');
        $setting = new WP_Setting(
            'kcl_parent',
            'Legend',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('Kept Label', $output);
        $this->assertStringContainsString('<th scope="row"', $output);
    }

    public function test_nested_container_does_not_emit_extra_fullwidth_fix(): void
    {
        // A nested advanced child must not retarget the parent's <tr>: only the
        // top-level container emits the full-width fix (exactly once).
        $grandchild = new WP_Setting('nest_gc', 'GC', 'text', 'general', 'main');
        $nested = new WP_Setting(
            'nest_adv',
            'Nested',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$grandchild]]
        );
        $setting = $this->makeAdvancedWith($nested);
        $setting->init(); // parent registered; children init(false), flag stays false

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        $this->assertSame(1, substr_count($output, 'document.currentScript'));
    }

    public function test_advanced_not_registered_omits_fullwidth_fix(): void
    {
        // Rendering the callback without registration (e.g. as a nested child)
        // must not emit the row fix-up.
        $child = new WP_Setting('nofix_child', 'Child', 'text', 'general', 'main');

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced(); // no init() -> flag false
        $output = ob_get_clean();

        $this->assertStringNotContainsString('document.currentScript', $output);
    }

    public function test_advanced_repeater_child_round_trips_saved_value(): void
    {
        $this->setOption('my_plugin_rt_rep', json_encode([['label' => 'SavedRowValue']]));

        $child = new WP_Setting(
            'rt_rep',
            'Round Trip Repeater',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']]]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        // The previously-saved row value renders back into the container.
        $this->assertStringContainsString('SavedRowValue', $output);
    }

    // -------------------------------------------------------------------------
    // Fix 2: textarea args passthrough (rows, class, placeholder)
    // -------------------------------------------------------------------------

    public function test_textarea_renders_rows_from_args(): void
    {
        $setting = new WP_Setting(
            'ta_rows',
            'Textarea Rows',
            'textarea',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['rows' => 8]
        );

        ob_start();
        $setting->init_textarea();
        $output = ob_get_clean();

        $this->assertStringContainsString('rows="8"', $output);
    }

    public function test_textarea_renders_class_from_args(): void
    {
        $setting = new WP_Setting(
            'ta_class',
            'Textarea Class',
            'textarea',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['class' => 'large-text']
        );

        ob_start();
        $setting->init_textarea();
        $output = ob_get_clean();

        $this->assertStringContainsString('class="large-text"', $output);
    }

    public function test_textarea_renders_placeholder_from_args(): void
    {
        $setting = new WP_Setting(
            'ta_placeholder',
            'Textarea Placeholder',
            'textarea',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['placeholder' => 'Enter text here']
        );

        ob_start();
        $setting->init_textarea();
        $output = ob_get_clean();

        $this->assertStringContainsString('placeholder="Enter text here"', $output);
    }

    // -------------------------------------------------------------------------
    // Fix 3: init_advanced details style overridable via args['style']
    // -------------------------------------------------------------------------

    public function test_init_advanced_default_style_has_no_margin_when_overridden(): void
    {
        $child = new WP_Setting('adv_child', 'Child', 'text', 'general', 'main');

        $setting = new WP_Setting(
            'adv_no_margin',
            'Advanced',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child], 'style' => 'padding: 15px;']
        );

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('style="padding: 15px;"', $output);
        $this->assertStringNotContainsString('margin-top: 20px', $output);
    }

    public function test_init_advanced_default_style_includes_margin_top(): void
    {
        $child = new WP_Setting('adv_child2', 'Child', 'text', 'general', 'main');

        $setting = new WP_Setting(
            'adv_default_margin',
            'Advanced',
            'advanced',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        ob_start();
        $setting->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('margin-top: 20px', $output);
    }

    // -------------------------------------------------------------------------
    // Bug: fieldset children must not register as standalone settings fields
    // -------------------------------------------------------------------------

    public function test_fieldset_children_do_not_register_standalone_fields(): void
    {
        $child = new WP_Setting('child_option', 'Child', 'text', 'general', 'main');

        $setting = new WP_Setting(
            'fieldset_option',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );

        $setting->init();

        $fields = $this->getRegisteredSettingsFields();

        $this->assertArrayHasKey('my_plugin_fieldset_option_field', $fields);
        $this->assertArrayNotHasKey('my_plugin_child_option_field', $fields);
    }

    public function test_fieldset_children_registered_once_not_duplicated(): void
    {
        $child1 = new WP_Setting('dup_child_a', 'Child A', 'text', 'general', 'main');
        $child2 = new WP_Setting('dup_child_b', 'Child B', 'checkbox', 'general', 'main');

        $setting = new WP_Setting(
            'dup_fieldset',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child1, $child2]]
        );

        $setting->init();

        $fields = $this->getRegisteredSettingsFields();

        // Parent registers; children must not appear as top-level rows
        $this->assertArrayHasKey('my_plugin_dup_fieldset_field', $fields);
        $this->assertArrayNotHasKey('my_plugin_dup_child_a_field', $fields);
        $this->assertArrayNotHasKey('my_plugin_dup_child_b_field', $fields);
    }

    // -------------------------------------------------------------------------
    // Feature: numbered_rows arg for repeater fields
    // -------------------------------------------------------------------------

    public function test_repeater_numbered_rows_adds_wrapper_class(): void
    {
        $setting = new WP_Setting(
            'rep_numbered',
            'Repeater',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'numbered_rows' => true,
                'children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]
        );

        ob_start();
        $setting->init_repeater();
        $output = ob_get_clean();

        $this->assertStringContainsString('wps-repeater-numbered', $output);
        $this->assertStringContainsString('wps-repeater-number-header', $output);
        $this->assertStringContainsString('wps-repeater-row-number', $output);
    }

    public function test_repeater_numbered_rows_no_number_cells_when_disabled(): void
    {
        $setting = new WP_Setting(
            'rep_plain2',
            'Repeater',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]
        );

        ob_start();
        $setting->init_repeater();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('wps-repeater-row-number', $output);
        $this->assertStringNotContainsString('wps-repeater-number-header', $output);
    }

    /* =========================================================
     * preserve_percent_encoded — opt-in to keep %XX sequences
     *   Default (sanitize_text_field / sanitize_textarea_field) strips %XX,
     *   destroying SOQL LIKE patterns, URL-encoded values, etc.
     * ======================================================= */

    public function test_repeater_strips_percent_encoded_by_default(): void
    {
        $setting = new WP_Setting(
            'r_default',
            'r',
            'repeater',
            'tab',
            'sec',
            null,
            null,
            false,
            array(),
            null,
            array(
                'children' => array(
                    array('name' => 'field', 'type' => 'text'),
                    array('name' => 'values', 'type' => 'textarea'),
                ),
            )
        );

        $out = $setting->sanitize_repeater(array(
            array('field' => 'F', 'values' => '%DA2%'),
        ));

        // %DA matches /%[a-f0-9]{2}/i and gets stripped; '2%' is what's left.
        $this->assertSame('2%', $out[0]['values']);
    }

    public function test_repeater_preserves_percent_encoded_when_opted_in(): void
    {
        $setting = new WP_Setting(
            'r_keep',
            'r',
            'repeater',
            'tab',
            'sec',
            null,
            null,
            false,
            array(),
            null,
            array(
                'children' => array(
                    array('name' => 'field', 'type' => 'text'),
                    array(
                        'name' => 'values',
                        'type' => 'textarea',
                        'preserve_percent_encoded' => true,
                    ),
                ),
            )
        );

        $out = $setting->sanitize_repeater(array(
            array('field' => 'F', 'values' => "%DA2%\nGNSS%"),
        ));

        $this->assertSame("%DA2%\nGNSS%", $out[0]['values']);
    }

    public function test_repeater_preserve_percent_still_strips_html(): void
    {
        $setting = new WP_Setting(
            'r_keep_html',
            'r',
            'repeater',
            'tab',
            'sec',
            null,
            null,
            false,
            array(),
            null,
            array(
                'children' => array(
                    array(
                        'name' => 'values',
                        'type' => 'text',
                        'preserve_percent_encoded' => true,
                    ),
                ),
            )
        );

        $out = $setting->sanitize_repeater(array(
            array('values' => 'DA2%<script>alert(1)</script>'),
        ));

        // % preserved, tags + their content stripped.
        $this->assertStringStartsWith('DA2%', $out[0]['values']);
        $this->assertStringNotContainsString('<script', $out[0]['values']);
        $this->assertStringNotContainsString('alert', $out[0]['values']);
    }

    public function test_repeater_numbered_rows_false_omits_wrapper_class(): void
    {
        $setting = new WP_Setting(
            'rep_plain',
            'Repeater',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            [
                'children' => [['name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]
        );

        ob_start();
        $setting->init_repeater();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('wps-repeater-numbered', $output);
    }

    // -------------------------------------------------------------------------
    // Fix 5: text-like input args passthrough (min, max, step, …)
    // -------------------------------------------------------------------------

    /**
     * Render a text-like field with the given args and return the markup.
     *
     * @param string $type Field type.
     * @param array  $args Type-specific args.
     * @return string
     */
    private function renderTextInput(string $type, array $args): string
    {
        $setting = new WP_Setting(
            'attr_field',
            'Attr Field',
            $type,
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            $args
        );

        ob_start();
        $setting->render_unbound(null, 'attr_field', 'attr_field');
        return ob_get_clean();
    }

    public function test_number_renders_min_max_step_from_args(): void
    {
        $output = $this->renderTextInput('number', ['min' => 60, 'max' => 86400, 'step' => 60]);

        $this->assertStringContainsString('min="60"', $output);
        $this->assertStringContainsString('max="86400"', $output);
        $this->assertStringContainsString('step="60"', $output);
    }

    public function test_number_renders_zero_min_and_non_numeric_step(): void
    {
        $output = $this->renderTextInput('number', ['min' => 0, 'step' => 'any']);

        $this->assertStringContainsString('min="0"', $output);
        $this->assertStringContainsString('step="any"', $output);
    }

    public function test_text_renders_pattern_and_length_attributes(): void
    {
        $output = $this->renderTextInput('text', [
            'pattern'      => '[A-Z]{3}',
            'minlength'    => 3,
            'maxlength'    => 3,
            'size'         => 10,
            'autocomplete' => 'off',
        ]);

        $this->assertStringContainsString('pattern="[A-Z]{3}"', $output);
        $this->assertStringContainsString('minlength="3"', $output);
        $this->assertStringContainsString('maxlength="3"', $output);
        $this->assertStringContainsString('size="10"', $output);
        $this->assertStringContainsString('autocomplete="off"', $output);
    }

    public function test_passthrough_attributes_are_escaped(): void
    {
        $output = $this->renderTextInput('text', ['pattern' => '"><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_passthrough_skips_empty_and_non_scalar_args(): void
    {
        $output = $this->renderTextInput('number', ['min' => '', 'max' => ['bad'], 'step' => null]);

        $this->assertStringNotContainsString('min=', $output);
        $this->assertStringNotContainsString('max=', $output);
        $this->assertStringNotContainsString('step=', $output);
    }

    public function test_passthrough_ignores_unlisted_attributes(): void
    {
        $output = $this->renderTextInput('text', ['onclick' => 'alert(1)', 'formaction' => '/evil']);

        $this->assertStringNotContainsString('onclick=', $output);
        $this->assertStringNotContainsString('formaction=', $output);
    }

    // -------------------------------------------------------------------------
    // Issue 7: presence-based rendering for boolean input attributes
    // -------------------------------------------------------------------------

    /**
     * Render a textarea with the given args and return the markup.
     *
     * @param array $args Type-specific args.
     * @return string
     */
    private function renderTextarea(array $args): string
    {
        $setting = new WP_Setting(
            'ta_bool',
            'Textarea Bool',
            'textarea',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            $args
        );

        ob_start();
        $setting->init_textarea();
        return ob_get_clean();
    }

    public function test_readonly_renders_bare_attribute_when_truthy(): void
    {
        $output = $this->renderTextInput('text', ['readonly' => true]);

        $this->assertStringContainsString(' readonly', $output);
        $this->assertStringNotContainsString('readonly=', $output);
    }

    /**
     * A boolean attribute is presence-based, so every falsy arg must omit it
     * entirely — readonly="0" would still lock the field.
     */
    public function test_readonly_omitted_for_falsy_args(): void
    {
        $falsy = [
            'false'        => false,
            'int zero'     => 0,
            'string zero'  => '0',
            'empty string' => '',
            'null'         => null,
        ];

        foreach ($falsy as $label => $value) {
            $output = $this->renderTextInput('text', ['readonly' => $value]);

            $this->assertStringNotContainsString('readonly', $output, "readonly rendered for {$label}");
        }
    }

    public function test_readonly_not_rendered_when_arg_absent(): void
    {
        $output = $this->renderTextInput('text', ['size' => 10]);

        $this->assertStringNotContainsString('readonly', $output);
    }

    public function test_readonly_coexists_with_value_passthrough_attributes(): void
    {
        $output = $this->renderTextInput('number', ['min' => 0, 'readonly' => true]);

        $this->assertStringContainsString('min="0"', $output);
        $this->assertStringContainsString(' readonly', $output);
    }

    /**
     * `disabled` stays out of the boolean list: browsers drop disabled inputs
     * from the POST body, which would blank the stored option on save.
     */
    public function test_disabled_is_not_a_passthrough_attribute(): void
    {
        $output = $this->renderTextInput('text', ['disabled' => true]);

        $this->assertStringNotContainsString('disabled', $output);
    }

    public function test_textarea_renders_readonly_from_args(): void
    {
        $output = $this->renderTextarea(['readonly' => true]);

        $this->assertStringContainsString(' readonly', $output);
        $this->assertStringNotContainsString('readonly=', $output);
    }

    public function test_textarea_omits_readonly_when_falsy(): void
    {
        $output = $this->renderTextarea(['readonly' => 0]);

        $this->assertStringNotContainsString('readonly', $output);
    }

    /**
     * The value-based list is invalid on a textarea, so only the boolean list
     * is wired into that renderer.
     */
    public function test_textarea_does_not_render_value_passthrough_attributes(): void
    {
        $output = $this->renderTextarea(['min' => 5, 'pattern' => '[A-Z]+', 'size' => 10]);

        $this->assertStringNotContainsString('min=', $output);
        $this->assertStringNotContainsString('pattern=', $output);
        $this->assertStringNotContainsString('size=', $output);
    }

    // -------------------------------------------------------------------------
    // Row labels: do_settings_fields() only wraps a row heading in
    // <label for> when the field declares label_for (issue #9).
    // -------------------------------------------------------------------------

    /** Register one field and return the args WordPress received for it. */
    private function registeredArgsFor(string $name, string $type, array $args = [], bool $required = false): array
    {
        $setting = new WP_Setting($name, 'A Title', $type, 'general', 'main', null, null, $required, null, null, $args);
        $setting->init();

        $fields = $this->getRegisteredSettingsFields();

        return $fields['my_plugin_' . $name . '_field']['args'];
    }

    /**
     * Every type rendered as one control with id="{slug}" — including a custom
     * input type falling through to the text renderer — gets the row label.
     */
    public function test_label_for_defaults_to_slug_for_single_control_types(): void
    {
        foreach (['text', 'email', 'url', 'number', 'password', 'color', 'date', 'textarea', 'select', 'checkbox'] as $type) {
            $args = $this->registeredArgsFor('lf_' . $type, $type);

            $this->assertSame(
                'my_plugin_lf_' . $type,
                $args['label_for'] ?? null,
                "label_for missing for type {$type}"
            );
        }
    }

    /**
     * Types with no control carrying the slug must not get a label pointing at
     * one — that trades a missing label for an orphan label.
     */
    public function test_label_for_omitted_for_types_without_a_matching_control(): void
    {
        foreach (['radio', 'sortable', 'table', 'field_map', 'repeater', 'richtext'] as $type) {
            $args = $this->registeredArgsFor('nolf_' . $type, $type);

            $this->assertArrayNotHasKey('label_for', $args, "label_for set for type {$type}");
        }
    }

    public function test_label_for_omitted_for_container_types(): void
    {
        $child = new WP_Setting('lf_container_child', 'Child', 'text', 'general', 'main');

        foreach (['advanced', 'fieldset'] as $type) {
            $args = $this->registeredArgsFor('lf_' . $type, $type, ['children' => [$child]]);

            $this->assertArrayNotHasKey('label_for', $args, "label_for set for type {$type}");
        }
    }

    public function test_label_for_from_args_is_preserved(): void
    {
        $args = $this->registeredArgsFor('lf_custom', 'text', ['label_for' => 'some_other_id']);

        $this->assertSame('some_other_id', $args['label_for']);
    }

    /**
     * WordPress tests label_for with `! empty()`, so an explicit empty string is
     * a consumer opting out of the generated label.
     */
    public function test_empty_label_for_from_args_is_preserved_as_opt_out(): void
    {
        $args = $this->registeredArgsFor('lf_optout', 'text', ['label_for' => '']);

        $this->assertSame('', $args['label_for']);
    }

    /**
     * The required marker is appended to the title, which ends up inside the
     * generated label — the label itself is still bound to the control.
     */
    public function test_required_field_keeps_label_for_and_required_marker(): void
    {
        $setting = new WP_Setting('lf_required', 'A Title', 'text', 'general', 'main', null, null, true);
        $setting->init();

        $field = $this->getRegisteredSettingsFields()['my_plugin_lf_required_field'];

        $this->assertSame('my_plugin_lf_required', $field['args']['label_for']);
        $this->assertStringContainsString('<span class="required">*</span>', $field['title']);
    }

    // -------------------------------------------------------------------------
    // Container children: the container renders its own headings, so the same
    // labelable/unlabelable split applies there.
    // -------------------------------------------------------------------------

    /** Build a fieldset container around one child. */
    private function makeFieldsetWith(WP_Setting $child): WP_Setting
    {
        return new WP_Setting(
            'lf_fs_parent',
            'Fieldset',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child]]
        );
    }

    public function test_fieldset_child_heading_binds_to_labelable_child(): void
    {
        $child = new WP_Setting('fs_text_child', 'Text Child', 'text', 'general', 'main');

        ob_start();
        $this->makeFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('<label for="my_plugin_fs_text_child">Text Child</label>', $output);
    }

    public function test_fieldset_child_heading_omits_for_on_unlabelable_child(): void
    {
        $child = new WP_Setting(
            'fs_radio_child',
            'Radio Child',
            'radio',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['a' => 'A', 'b' => 'B']]
        );

        ob_start();
        $this->makeFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString('<th scope="row">Radio Child</th>', $output);
        $this->assertStringNotContainsString('for="my_plugin_fs_radio_child"', $output);
    }

    public function test_advanced_child_heading_binds_to_labelable_child(): void
    {
        $child = new WP_Setting('adv_text_child', 'Text Child', 'text', 'general', 'main');

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '<label for="my_plugin_adv_text_child"><strong>Text Child</strong></label>',
            $output
        );
    }

    public function test_advanced_child_heading_omits_for_on_unlabelable_child(): void
    {
        $child = new WP_Setting(
            'adv_radio_child',
            'Radio Child',
            'radio',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['a' => 'A', 'b' => 'B']]
        );

        ob_start();
        $this->makeAdvancedWith($child)->init_advanced();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p><strong>Radio Child</strong></p>', $output);
        $this->assertStringNotContainsString('for="my_plugin_adv_radio_child"', $output);
    }

    // -------------------------------------------------------------------------
    // Accessible names the label_for fix did not reach (issue #14): children
    // under hide_child_labels, and repeater cells.
    // -------------------------------------------------------------------------

    /** Build a hide_child_labels fieldset around one child. */
    private function makeHiddenLabelFieldsetWith(WP_Setting $child): WP_Setting
    {
        return new WP_Setting(
            'a11y_fs_parent',
            'Group Legend',
            'fieldset',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => [$child], 'hide_child_labels' => true]
        );
    }

    /**
     * A select has no naming source of its own, so dropping the <th> left it
     * unnamed (axe: select-name).
     */
    public function test_hidden_child_label_still_names_a_select(): void
    {
        $child = new WP_Setting(
            'a11y_select_child',
            'Sync Frequency',
            'select',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['daily' => 'Daily', 'weekly' => 'Weekly']]
        );

        ob_start();
        $this->makeHiddenLabelFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<th scope="row"', $output);
        $this->assertStringContainsString(
            '<label class="screen-reader-text" for="my_plugin_a11y_select_child">Sync Frequency</label>',
            $output
        );
    }

    /**
     * A checkbox already labels itself with its description; a second label for
     * the same control is its own violation (axe: form-field-multiple-labels).
     */
    public function test_hidden_child_label_skipped_for_self_labelling_checkbox(): void
    {
        $child = new WP_Setting(
            'a11y_cb_child',
            'Enable Sync',
            'checkbox',
            'general',
            'main',
            null,
            'Sync products nightly'
        );

        ob_start();
        $this->makeHiddenLabelFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('screen-reader-text', $output);
        $this->assertSame(1, substr_count($output, 'for="my_plugin_a11y_cb_child"'));
    }

    /** A checkbox with no description has no label of its own to keep. */
    public function test_hidden_child_label_names_a_checkbox_without_a_description(): void
    {
        $child = new WP_Setting('a11y_cb_bare', 'Enable Sync', 'checkbox', 'general', 'main');

        ob_start();
        $this->makeHiddenLabelFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '<label class="screen-reader-text" for="my_plugin_a11y_cb_bare">Enable Sync</label>',
            $output
        );
    }

    /** Nothing carries the child's slug, so a label would be an orphan. */
    public function test_hidden_child_label_omitted_for_unlabelable_child(): void
    {
        $child = new WP_Setting(
            'a11y_radio_child',
            'Radio Child',
            'radio',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['options' => ['a' => 'A']]
        );

        ob_start();
        $this->makeHiddenLabelFieldsetWith($child)->init_fieldset();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('for="my_plugin_a11y_radio_child"', $output);
    }

    /** Build a repeater over the three cell renderers. */
    private function makeRepeater(string $slug, array $children): WP_Setting
    {
        return new WP_Setting(
            $slug,
            'Rows',
            'repeater',
            'general',
            'main',
            null,
            null,
            false,
            null,
            null,
            ['children' => $children]
        );
    }

    /**
     * A column <th> is not an accessible name for a control, so every cell
     * renderer has to name its own control.
     */
    public function test_repeater_cells_are_named_by_column_and_row(): void
    {
        $setting = $this->makeRepeater('a11y_rep', [
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => ['a' => 'A']],
        ]);

        ob_start();
        $setting->render_unbound([['key' => 'k', 'notes' => 'n', 'mode' => 'a']], 'a11y_rep', 'a11y_rep');
        $output = ob_get_clean();

        $this->assertStringContainsString('aria-label="Key, row 1"', $output);
        $this->assertStringContainsString('aria-label="Notes, row 1"', $output);
        $this->assertStringContainsString('aria-label="Mode, row 1"', $output);
    }

    /** Rows are numbered from one, matching the visible counter. */
    public function test_repeater_row_numbers_are_one_based(): void
    {
        $setting = $this->makeRepeater('a11y_rep_rows', [
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
        ]);

        ob_start();
        $setting->render_unbound([['key' => 'a'], ['key' => 'b']], 'a11y_rep_rows', 'a11y_rep_rows');
        $output = ob_get_clean();

        $this->assertStringContainsString('aria-label="Key, row 1"', $output);
        $this->assertStringContainsString('aria-label="Key, row 2"', $output);
        $this->assertStringNotContainsString('row 0', $output);
    }

    /**
     * The template row has no position until it is inserted, so it carries the
     * bare column label and the row script numbers it on add.
     */
    public function test_repeater_template_row_is_named_without_a_position(): void
    {
        $setting = $this->makeRepeater('a11y_rep_tmpl', [
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
        ]);

        ob_start();
        $setting->render_unbound([], 'a11y_rep_tmpl', 'a11y_rep_tmpl');
        $output = ob_get_clean();

        $this->assertStringContainsString('data-label="Key"', $output);
        $this->assertStringContainsString('aria-label="Key"', $output);
        $this->assertStringNotContainsString('row __INDEX__', $output);
        $this->assertStringContainsString('relabelRows', $output);
    }

    /** With no column label to borrow, the field name is the name. */
    public function test_repeater_cell_falls_back_to_the_field_name(): void
    {
        $setting = $this->makeRepeater('a11y_rep_nolabel', [
            ['name' => 'sku', 'type' => 'text'],
        ]);

        ob_start();
        $setting->render_unbound([['sku' => 'x']], 'a11y_rep_nolabel', 'a11y_rep_nolabel');
        $output = ob_get_clean();

        $this->assertStringContainsString('aria-label="sku, row 1"', $output);
    }
}
