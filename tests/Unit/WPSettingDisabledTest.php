<?php

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A field declared `disabled`: rendered inert, its stored value kept on save.
 */
class WPSettingDisabledTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Setting::$text_domain = 'my-plugin';
    }

    private function setting(string $type, array $args = array('disabled' => true)): WP_Setting
    {
        return new WP_Setting('hide_quotes', 'Hide Quote Screens', $type, 'general', 'main', null, 'Requires the quote plugin.', false, null, null, $args);
    }

    private function render(WP_Setting $setting): string
    {
        ob_start();
        $setting->init();
        call_user_func($GLOBALS['wp_test_settings_fields'][$setting->slug . '_field']['callback']);

        return (string) ob_get_clean();
    }

    public function test_a_disabled_checkbox_renders_inside_a_disabled_fieldset(): void
    {
        $GLOBALS['wp_test_options']['my_plugin_hide_quotes'] = '1';

        $output = $this->render($this->setting('checkbox'));

        $this->assertMatchesRegularExpression('#^<fieldset class="wps-disabled" disabled[^>]*>.*<input id="my_plugin_hide_quotes" type="checkbox" name="my_plugin_hide_quotes" value="on" checked="checked">.*</fieldset>$#s', $output);
    }

    #[DataProvider('types')]
    public function test_every_bound_type_is_wrapped(string $type, array $args): void
    {
        $output = $this->render($this->setting($type, $args + array('disabled' => true)));

        $this->assertStringStartsWith('<fieldset class="wps-disabled" disabled', $output);
        $this->assertStringEndsWith('</fieldset>', $output);
    }

    public static function types(): array
    {
        $options = array('options' => array('a' => 'A'));

        return array(
            'text'      => array('text', array()),
            'textarea'  => array('textarea', array()),
            'select'    => array('select', $options),
            'radio'     => array('radio', $options),
            'repeater'  => array('repeater', array('children' => array(array('name' => 'label', 'label' => 'Label', 'type' => 'text')))),
            'field_map' => array('field_map', $options),
        );
    }

    public function test_a_field_without_disabled_renders_no_fieldset(): void
    {
        $this->assertStringNotContainsString('wps-disabled', $this->render($this->setting('checkbox', array())));
        $this->assertStringNotContainsString('wps-disabled', $this->render($this->setting('checkbox', array('disabled' => false))));
    }

    public function test_save_keeps_the_stored_value(): void
    {
        $GLOBALS['wp_test_options']['my_plugin_hide_quotes'] = '1';
        $_POST['my_plugin_hide_quotes'] = '0';

        $this->setting('checkbox')->save();

        unset($_POST['my_plugin_hide_quotes']);
        $this->assertSame('1', $GLOBALS['wp_test_options']['my_plugin_hide_quotes']);
    }

    public function test_the_registered_sanitizer_answers_with_the_stored_value(): void
    {
        $setting = $this->setting('text', array('disabled' => true, 'sanitize_callback' => fn() => 'changed'));
        $setting->init();
        $GLOBALS['wp_test_options']['my_plugin_hide_quotes'] = 'kept';

        $sanitize = $GLOBALS['wp_test_registered_settings']['my_plugin_hide_quotes']['sanitize_callback'];

        $this->assertSame('kept', $sanitize(null));
        $this->assertSame('kept', $sanitize('forged'));
    }

    public function test_an_encrypted_disabled_field_keeps_its_ciphertext(): void
    {
        $setting = $this->setting('text', array('disabled' => true, 'encrypted' => true));
        $setting->init();
        $GLOBALS['wp_test_options']['my_plugin_hide_quotes'] = 'ciphertext';

        $sanitize = $GLOBALS['wp_test_registered_settings']['my_plugin_hide_quotes']['sanitize_callback'];

        $this->assertSame('ciphertext', $sanitize(null));
    }

    public function test_a_container_ignores_disabled_and_saves_its_children(): void
    {
        $child     = new WP_Setting('note', 'Note', 'text', 'general', 'main');
        $container = new WP_Setting('group', 'Group', 'fieldset', 'general', 'main', null, null, false, null, null, array('disabled' => true, 'children' => array($child)));
        $_POST['my_plugin_note'] = 'saved';

        $container->save();

        unset($_POST['my_plugin_note']);
        $this->assertFalse($container->is_disabled());
        $this->assertSame('saved', $GLOBALS['wp_test_options']['my_plugin_note']);
    }
}
