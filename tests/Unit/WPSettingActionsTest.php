<?php

use BGoewert\WP_Settings\WP_Setting;

/**
 * Buttons a field declares through `actions`, and the forms they submit.
 */
class WPSettingActionsTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Setting::$text_domain = 'my-plugin';
        // Drain forms a previous test rendered.
        ob_start();
        WP_Setting::render_action_forms();
        ob_end_clean();
    }

    private function setting(array $actions): WP_Setting
    {
        return new WP_Setting('inbound_secret', 'Inbound Secret', 'password', 'general', 'main', null, 'Shared with the portal.', false, null, null, array('actions' => $actions));
    }

    private function render(WP_Setting $setting): string
    {
        ob_start();
        $setting->init_type();

        return (string) ob_get_clean();
    }

    private function footer(): string
    {
        ob_start();
        WP_Setting::render_action_forms();

        return (string) ob_get_clean();
    }

    public function test_each_action_renders_a_button_named_after_the_field(): void
    {
        $output = $this->render($this->setting(array(
            array('label' => 'Generate', 'action' => 'my_generate'),
            array('label' => 'Rotate', 'action' => 'my_rotate'),
        )));

        $this->assertStringContainsString(
            '<button type="submit" class="button wps-field-action" form="wps-action-my_plugin_inbound_secret-my_generate">Generate<span class="screen-reader-text"> Inbound Secret</span></button>',
            $output
        );
        $this->assertStringContainsString('form="wps-action-my_plugin_inbound_secret-my_rotate">Rotate', $output);
        $this->assertLessThan(strpos($output, 'wps-field-actions'), strpos($output, 'name="my_plugin_inbound_secret"'));
    }

    public function test_a_text_like_field_renders_its_actions_beside_the_input(): void
    {
        $output = $this->render($this->setting(array(array('label' => 'Generate', 'action' => 'my_generate'))));

        $this->assertMatchesRegularExpression('#<span class="text">Show</span></button> <span class="wps-field-actions"><button[^>]+>Generate#', $output);
        $this->assertLessThan(strpos($output, 'class="description"'), strpos($output, 'wps-field-actions'));
    }

    public function test_actions_position_below_moves_them_under_the_description(): void
    {
        $setting = new WP_Setting('inbound_secret', 'Inbound Secret', 'password', 'general', 'main', null, 'Shared with the portal.', false, null, null, array(
            'actions'          => array(array('label' => 'Generate', 'action' => 'my_generate')),
            'actions_position' => 'below',
        ));

        $output = $this->render($setting);

        $this->assertStringContainsString('<p class="wps-field-actions">', $output);
        $this->assertGreaterThan(strpos($output, 'class="description"'), strpos($output, 'wps-field-actions'));
    }

    public function test_a_type_that_cannot_sit_inline_renders_its_actions_below(): void
    {
        $setting = new WP_Setting('notes', 'Notes', 'textarea', 'general', 'main', null, null, false, null, null, array(
            'actions' => array(array('label' => 'Generate', 'action' => 'my_generate')),
        ));

        ob_start();
        $setting->init_textarea();
        $output = (string) ob_get_clean();

        $this->assertSame(1, substr_count($output, 'wps-field-actions'));
        $this->assertStringContainsString('<p class="wps-field-actions">', $output);
    }

    public function test_a_field_without_actions_renders_no_buttons_or_forms(): void
    {
        $output = $this->render($this->setting(array()));

        $this->assertStringNotContainsString('wps-field-action', $output);
        $this->assertSame('', $this->footer());
    }

    public function test_the_button_is_hidden_from_a_user_without_the_capability(): void
    {
        $GLOBALS['wp_test_denied_capabilities'] = array('manage_portal');

        $output = $this->render($this->setting(array(
            array('label' => 'Generate', 'action' => 'my_generate', 'capability' => 'manage_portal'),
        )));

        $this->assertStringNotContainsString('wps-field-action', $output);
        $this->assertSame('', $this->footer());
    }

    public function test_an_entry_without_a_label_or_a_hook_safe_name_is_dropped(): void
    {
        $setting = $this->setting(array(
            array('action' => 'my_generate'),
            array('label' => 'Rotate', 'action' => 'my rotate"'),
            'not an array',
            array('label' => 'Generate', 'action' => 'my_generate'),
        ));

        $this->assertSame(
            array(array('label' => 'Generate', 'action' => 'my_generate', 'capability' => 'manage_options')),
            $setting->field_actions()
        );
    }

    public function test_the_footer_carries_one_form_per_button_posting_to_admin_post(): void
    {
        $this->render($this->setting(array(array('label' => 'Generate', 'action' => 'my_generate'))));

        $footer = $this->footer();

        $this->assertStringContainsString('<form id="wps-action-my_plugin_inbound_secret-my_generate" method="post" action="http://example.com/wp-admin/admin-post.php" hidden>', $footer);
        $this->assertStringContainsString('<input type="hidden" name="action" value="my_generate">', $footer);
        $this->assertStringContainsString('<input type="hidden" name="setting" value="my_plugin_inbound_secret">', $footer);
        $this->assertStringContainsString('name="_wpnonce" value="nonce-my_generate"', $footer);
        $this->assertSame('', $this->footer(), 'Forms render once.');
    }

    public function test_the_footer_hook_is_added_once_per_request(): void
    {
        $this->render($this->setting(array(
            array('label' => 'Generate', 'action' => 'my_generate'),
            array('label' => 'Rotate', 'action' => 'my_rotate'),
        )));

        $this->assertCount(1, $GLOBALS['wp_test_actions']['admin_footer']);
    }

    public function test_init_brackets_the_consumer_handler_with_the_checks_and_the_redirect(): void
    {
        $this->setting(array(array('label' => 'Generate', 'action' => 'my_generate')))->init();

        $hooks = $GLOBALS['wp_test_actions']['admin_post_my_generate'];

        $this->assertSame(array(0, PHP_INT_MAX), array_column($hooks, 'priority'));
        $this->assertSame(array(WP_Setting::class, 'return_from_action'), $hooks[1]['callback']);
    }

    public function test_a_request_without_the_capability_is_stopped(): void
    {
        $GLOBALS['wp_test_denied_capabilities'] = array('manage_portal');
        $this->setting(array(array('label' => 'Generate', 'action' => 'my_generate', 'capability' => 'manage_portal')))->init();

        $this->expectExceptionMessage('wp_die');
        call_user_func($GLOBALS['wp_test_actions']['admin_post_my_generate'][0]['callback']);
    }
}
