<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings_Email;

/**
 * An `email_preview` row: an email shown as it will be sent, with nothing stored.
 */
class WPSettingEmailPreviewTest extends WP_Settings_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Setting::$text_domain = 'my-plugin';
    }

    private function setting(array $args, $description = null): WP_Setting
    {
        return new WP_Setting('notice_preview', 'Notice Preview', 'email_preview', 'general', 'main', null, $description, false, null, null, $args + array(
            'email' => new WP_Settings_Email(array('id' => 'notice', 'subject' => 'Hello {name}', 'body' => 'Hi {name}', 'sample' => array('name' => 'Jane'))),
        ));
    }

    private function render(WP_Setting $setting): string
    {
        ob_start();
        $setting->init_email_preview();

        return (string) ob_get_clean();
    }

    public function test_the_row_shows_the_sample_rendered(): void
    {
        $output = $this->render($this->setting(array(), 'What attendees get.'));

        $this->assertStringContainsString('<span data-wps-email-subject>Hello Jane</span>', $output);
        $this->assertStringContainsString('<figure class="wps-email-preview" aria-label="Preview: Notice Preview"', $output);
        $this->assertMatchesRegularExpression('~<pre[^>]*>Hi Jane</pre>~', $output);
        $this->assertStringContainsString('<p class="description">What attendees get.</p>', $output);
    }

    public function test_watch_names_fields_unprefixed_and_resolves_to_their_ids(): void
    {
        $output = $this->render($this->setting(array('watch' => array('subject' => 'notice_subject', 'body' => 'notice_body'))));

        preg_match('~class="wps-email-preview-data">(.*?)</script>~s', $output, $m);
        $this->assertSame(array('subject' => 'my_plugin_notice_subject', 'body' => 'my_plugin_notice_body'), json_decode($m[1], true)['watch']);
    }

    public function test_a_callable_email_is_built_at_render_not_at_construction(): void
    {
        $calls   = 0;
        $setting = $this->setting(array('email' => function () use (&$calls) {
            $calls++;
            return new WP_Settings_Email(array('id' => 'notice', 'subject' => 'Saved subject'));
        }));

        $this->assertSame(0, $calls);
        $this->assertStringContainsString('>Saved subject</span>', $this->render($setting));
        $this->assertSame(1, $calls);
    }

    public function test_a_missing_email_renders_nothing(): void
    {
        $setting = new WP_Setting('notice_preview', 'Notice Preview', 'email_preview', 'general', 'main', null, null, false, null, null, array('email' => 'not an email'));

        $this->assertSame('', $this->render($setting));
    }

    public function test_init_seeds_and_registers_no_option_and_has_no_label(): void
    {
        global $wp_test_options, $wp_test_settings_fields, $wp_test_registered_settings;

        $setting = $this->setting(array());
        $setting->init();

        $this->assertArrayNotHasKey('my_plugin_notice_preview', $wp_test_options);
        $this->assertArrayNotHasKey('my_plugin_notice_preview', (array) $wp_test_registered_settings);
        $this->assertSame(array($setting, 'init_email_preview'), $wp_test_settings_fields['my_plugin_notice_preview_field']['callback']);
        $this->assertArrayNotHasKey('label_for', $wp_test_settings_fields['my_plugin_notice_preview_field']['args']);
    }

    public function test_save_writes_nothing(): void
    {
        global $wp_test_options;
        $_POST['my_plugin_notice_preview'] = 'Forged';

        $this->setting(array())->save();

        unset($_POST['my_plugin_notice_preview']);
        $this->assertArrayNotHasKey('my_plugin_notice_preview', $wp_test_options);
    }
}
