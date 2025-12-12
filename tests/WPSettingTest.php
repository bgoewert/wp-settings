<?php

use BGoewert\WP_Settings\WP_Setting;

/**
 * Tests for WP_Setting class
 */
class WPSettingTest extends WP_Settings_TestCase
{
    /**
     * Test that constructor sets properties correctly
     */
    public function test_constructor_sets_properties(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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
        $this->assertSame('my-plugin_test_option', $setting->slug);
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
            'my-plugin',
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
            'my-plugin',
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
            'my-plugin',
            'test_option',
            'Test Option',
            'text',
            'general',
            'main'
        );

        $setting->init();

        // Check option was added
        $this->assertNotNull($this->getOption('my-plugin_test_option'));

        // Check settings field was registered
        $fields = $this->getRegisteredSettingsFields();
        $this->assertArrayHasKey('my-plugin_test_option_field', $fields);
    }

    /**
     * Test hidden field type does not add settings field
     */
    public function test_hidden_field_skips_settings_field(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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
        $this->assertArrayNotHasKey('my-plugin_hidden_option_field', $fields);
    }

    /**
     * Test advanced field type registers with empty title
     */
    public function test_advanced_field_registers_with_empty_title(): void
    {
        $child = new WP_Setting(
            'my-plugin',
            'child_option',
            'Child',
            'text',
            'general',
            'main'
        );

        $setting = new WP_Setting(
            'my-plugin',
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
        $this->assertArrayHasKey('my-plugin_advanced_option_field', $fields);
        // Title should be empty for advanced fields
        $this->assertSame('', $fields['my-plugin_advanced_option_field']['title']);
    }

    /**
     * Test children are extracted from args
     */
    public function test_children_extracted_from_args(): void
    {
        $child1 = new WP_Setting('my-plugin', 'child1', 'Child 1', 'text', 'general', 'main');
        $child2 = new WP_Setting('my-plugin', 'child2', 'Child 2', 'checkbox', 'general', 'main');

        $setting = new WP_Setting(
            'my-plugin',
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

        $this->setOption('my-plugin_my_option', 'test_value');

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

        $this->assertSame('new_value', $this->getOption('my-plugin_my_option'));
    }

    /**
     * Test init_type renders text input
     */
    public function test_init_type_renders_text_input(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
            'text_option',
            'Text Option',
            'text',
            'general',
            'main',
            '300px',
            'Enter text here'
        );

        $this->setOption('my-plugin_text_option', 'current_value');

        ob_start();
        $setting->init_type();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="my-plugin_text_option"', $output);
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
            'my-plugin',
            'checkbox_option',
            'Checkbox Option',
            'checkbox',
            'general',
            'main',
            null,
            'Enable this feature'
        );

        $this->setOption('my-plugin_checkbox_option', true);

        ob_start();
        $setting->init_checkbox();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="my-plugin_checkbox_option"', $output);
        $this->assertStringContainsString('checked="checked"', $output);
        $this->assertStringContainsString('Enable this feature', $output);
    }

    /**
     * Test init_textarea renders textarea
     */
    public function test_init_textarea_renders_textarea(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
            'textarea_option',
            'Textarea Option',
            'textarea',
            'general',
            'main',
            '500px',
            'Enter long text'
        );

        $this->setOption('my-plugin_textarea_option', 'multiline content');

        ob_start();
        $setting->init_textarea();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="my-plugin_textarea_option"', $output);
        $this->assertStringContainsString('multiline content', $output);
        $this->assertStringContainsString('style="width:500px;"', $output);
    }

    /**
     * Test init_select renders select dropdown
     */
    public function test_init_select_renders_select(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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

        $this->setOption('my-plugin_select_option', 'option2');

        ob_start();
        $setting->init_select();
        $output = ob_get_clean();

        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('name="my-plugin_select_option"', $output);
        $this->assertStringContainsString('Option 1', $output);
        $this->assertStringContainsString('Option 2', $output);
        $this->assertStringContainsString('Option 3', $output);
    }

    /**
     * Test init_hidden renders hidden input
     */
    public function test_init_hidden_renders_hidden_input(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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

        $this->setOption('my-plugin_hidden_option', 'hidden_value');

        ob_start();
        $setting->init_hidden();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="hidden"', $output);
        $this->assertStringContainsString('name="my-plugin_hidden_option"', $output);
        $this->assertStringContainsString('value="hidden_value"', $output);
    }

    /**
     * Test init_hidden handles array value safely
     */
    public function test_init_hidden_handles_array_value(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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
        $this->setOption('my-plugin_hidden_option', ['unexpected', 'array']);

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
            'my-plugin',
            'child_checkbox',
            'Child Checkbox',
            'checkbox',
            'general',
            'main',
            null,
            'Child description'
        );

        $setting = new WP_Setting(
            'my-plugin',
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

    /**
     * Test required attribute is added to inputs
     */
    public function test_required_attribute_added(): void
    {
        $setting = new WP_Setting(
            'my-plugin',
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
}
