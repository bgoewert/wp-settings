<?php

use BGoewert\WP_Settings\WP_Setting;

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
        $_POST['my-plugin_email_field'] = ' user@example.com ';
        $setting->save();

        // Check the value was sanitized
        $value = WP_Setting::get('email_field');
        $this->assertSame('user@example.com', $value);

        // Clean up
        unset($_POST['my-plugin_email_field']);
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
        $_POST['my-plugin_email_field'] = 'not an email';
        $setting->save();

        // Check the value was rejected (should return false)
        $value = WP_Setting::get('email_field');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my-plugin_email_field']);
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
        $_POST['my-plugin_url_field'] = 'https://example.com/path';
        $setting->save();

        // Check the value was sanitized
        $value = WP_Setting::get('url_field');
        $this->assertSame('https://example.com/path', $value);

        // Clean up
        unset($_POST['my-plugin_url_field']);
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
        $_POST['my-plugin_url_field'] = 'not a url';
        $setting->save();

        // Check the value was rejected
        $value = WP_Setting::get('url_field');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my-plugin_url_field']);
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
        $_POST['my-plugin_number_field'] = '42';
        $setting->save();

        // Check the value was saved
        $value = WP_Setting::get('number_field');
        $this->assertSame('42', $value);

        // Clean up
        unset($_POST['my-plugin_number_field']);
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
        $_POST['my-plugin_number_field'] = 'not a number';
        $setting->save();

        // Check the value was rejected (empty string)
        $value = WP_Setting::get('number_field');
        $this->assertSame('', $value);

        // Clean up
        unset($_POST['my-plugin_number_field']);
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
        $_POST['my-plugin_text_field'] = '<b>Bold</b> text';
        $setting->save();

        // Check the value was sanitized (tags removed but content kept)
        $value = WP_Setting::get('text_field');
        $this->assertSame('Bold text', $value);

        // Clean up
        unset($_POST['my-plugin_text_field']);
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
        $_POST['my-plugin_textarea_field'] = '<b>Bold text</b>';
        $setting->save();

        // Check the value was sanitized (tags removed)
        $value = WP_Setting::get('textarea_field');
        $this->assertSame('Bold text', $value);

        // Clean up
        unset($_POST['my-plugin_textarea_field']);
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
        $_POST['my-plugin_custom_email'] = 'user@example.com';
        $setting->save();

        // Check the custom callback was used (uppercase)
        $value = WP_Setting::get('custom_email');
        $this->assertSame('USER@EXAMPLE.COM', $value);

        // Clean up
        unset($_POST['my-plugin_custom_email']);
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
        $_POST['my-plugin_custom_select'] = 'a';
        $setting->save();

        // Check the custom callback was applied
        $value = WP_Setting::get('custom_select');
        $this->assertSame('sanitized_a', $value);

        // Clean up
        unset($_POST['my-plugin_custom_select']);
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
        $_POST['my-plugin_validated_email'] = ' test@example.com ';
        $setting->save();

        // Check the static method was called and email was sanitized
        $value = WP_Setting::get('validated_email');
        $this->assertSame('test@example.com', $value);

        // Clean up
        unset($_POST['my-plugin_validated_email']);
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
        $_POST['my-plugin_validated_number'] = '50';
        $setting->save();
        $value = WP_Setting::get('validated_number');
        $this->assertSame('50', $value);

        // Test invalid value (over 100)
        $_POST['my-plugin_validated_number'] = '150';
        $setting->save();
        $value = WP_Setting::get('validated_number');
        $this->assertFalse($value);

        // Clean up
        unset($_POST['my-plugin_validated_number']);
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
        $_POST['my-plugin_callback_test'] = 'test_value_123';
        $setting->save();

        // Check the callback received the correct value
        $this->assertSame('test_value_123', $received_value);

        // Clean up
        unset($_POST['my-plugin_callback_test']);
    }
}
