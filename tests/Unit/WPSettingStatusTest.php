<?php

use BGoewert\WP_Settings\WP_Setting;

/**
 * A `status` row: a derived reading shown as text, with nothing stored.
 */
class WPSettingStatusTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Setting::$text_domain = 'my-plugin';
        ob_start();
        WP_Setting::render_action_forms();
        ob_end_clean();
    }

    private function setting(array $args, $description = null): WP_Setting
    {
        return new WP_Setting('connection', 'Connection', 'status', 'general', 'main', null, $description, false, null, null, $args);
    }

    private function render(WP_Setting $setting): string
    {
        ob_start();
        $setting->init_status();

        return (string) ob_get_clean();
    }

    public function test_the_value_renders_as_text_with_no_input(): void
    {
        $output = $this->render($this->setting(array('value' => 'Connected'), 'Checked hourly.'));

        $this->assertStringContainsString('<span class="wps-status" id="my_plugin_connection">Connected</span>', $output);
        $this->assertStringContainsString('<p class="description">Checked hourly.</p>', $output);
        $this->assertStringNotContainsString('<input', $output);
    }

    public function test_a_callable_value_runs_at_render_not_at_construction(): void
    {
        $calls   = 0;
        $setting = $this->setting(array('value' => function () use (&$calls) {
            $calls++;
            return 'Last sync 2 minutes ago';
        }));

        $this->assertSame(0, $calls);
        $this->assertStringContainsString('>Last sync 2 minutes ago</span>', $this->render($setting));
        $this->assertSame(1, $calls);
    }

    public function test_init_seeds_and_registers_no_option(): void
    {
        global $wp_test_options, $wp_test_settings_fields;

        $setting = $this->setting(array('value' => 'Connected'));
        $setting->init();

        $this->assertArrayNotHasKey('my_plugin_connection', $wp_test_options);
        $this->assertArrayHasKey('my_plugin_connection_field', $wp_test_settings_fields);
        $this->assertSame(array($setting, 'init_status'), $wp_test_settings_fields['my_plugin_connection_field']['callback']);
        $this->assertArrayNotHasKey('label_for', $wp_test_settings_fields['my_plugin_connection_field']['args']);
    }

    public function test_save_writes_nothing(): void
    {
        global $wp_test_options;
        $_POST['my_plugin_connection'] = 'Forged';

        $this->setting(array('value' => 'Connected'))->save();

        unset($_POST['my_plugin_connection']);
        $this->assertArrayNotHasKey('my_plugin_connection', $wp_test_options);
    }

    public function test_actions_render_beside_the_value(): void
    {
        $output = $this->render($this->setting(array(
            'value'   => 'Connected',
            'actions' => array(array('label' => 'Test connection', 'action' => 'my_test')),
        ), 'Checked hourly.'));

        $this->assertMatchesRegularExpression('#Connected</span> <span class="wps-field-actions"><button[^>]+form="wps-action-my_plugin_connection-my_test">Test connection<span class="screen-reader-text"> Connection</span>#', $output);
        $this->assertLessThan(strpos($output, 'class="description"'), strpos($output, 'wps-field-actions'));
    }

    public function test_actions_position_below_moves_them_under_the_description(): void
    {
        $output = $this->render($this->setting(array(
            'value'            => 'Connected',
            'actions'          => array(array('label' => 'Test connection', 'action' => 'my_test')),
            'actions_position' => 'below',
        ), 'Checked hourly.'));

        $this->assertSame(1, substr_count($output, 'wps-field-actions'));
        $this->assertGreaterThan(strpos($output, 'class="description"'), strpos($output, '<p class="wps-field-actions">'));
    }
}
