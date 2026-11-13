<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings_Email;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * An email against real WordPress: core's filter API, wp_mail() and the Settings API.
 *
 * pre_wp_mail short-circuits delivery after wp_mail() has applied its own
 * filters, so what it receives is what PHPMailer would have been handed.
 */
#[Group('integration')]
final class EmailTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    /** @var array<int,array<string,mixed>> */
    private array $mailed = [];

    /** @var callable */
    private $capture;

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
        update_option('wp_settings_harness_mail_wrapper', 'brand');

        $this->capture = function ($short_circuit, array $atts) {
            $this->mailed[] = $atts;
            return true;
        };
        add_filter('pre_wp_mail', $this->capture, 10, 2);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_wp_mail', $this->capture, 10);
        delete_option('wp_settings_harness_mail_wrapper');

        parent::tearDown();
    }

    public function test_send_mails_the_site_wrapped_message_render_returns(): void
    {
        $email   = \WP_Settings_Harness::notice_email('{event_title}: moved', "Hi {attendee_name},\n\nSee https://example.com.");
        $context = ['event_title' => 'Fall Expo', 'attendee_name' => 'Sam'];

        $this->assertTrue($email->send('sam@example.com', $context));

        $rendered = $email->render($context, 'sam@example.com');
        $this->assertCount(1, $this->mailed);
        $this->assertSame('sam@example.com', $this->mailed[0]['to']);
        $this->assertSame('[Harness] Fall Expo: moved', $this->mailed[0]['subject']);
        $this->assertSame($rendered['message'], $this->mailed[0]['message']);
        $this->assertStringContainsString('<a href="https://example.com" style="color:#0b4f8a;">https://example.com</a>.', $this->mailed[0]['message']);
        $this->assertTrue(WP_Settings_Email::is_html($this->mailed[0]['headers']));
    }

    public function test_a_failed_send_is_reported(): void
    {
        $refuse = static fn() => false;
        add_filter('pre_wp_mail', $refuse, 20);

        try {
            $this->assertFalse(\WP_Settings_Harness::notice_email('S', 'B')->send('sam@example.com', []));
        } finally {
            remove_filter('pre_wp_mail', $refuse, 20);
        }
    }

    public function test_the_preview_field_registers_no_setting(): void
    {
        $registered = get_registered_settings();

        $this->assertArrayHasKey('wp_settings_harness_notice_body', $registered);
        $this->assertArrayNotHasKey('wp_settings_harness_notice_preview', $registered);
        $this->assertFalse(get_option('wp_settings_harness_notice_preview'));
    }

    public function test_the_preview_field_shows_the_saved_template_through_the_wrapper(): void
    {
        update_option('wp_settings_harness_notice_body', 'Saved for {attendee_name}');

        try {
            ob_start();
            (new WP_Setting('notice_preview', 'Notice Preview', 'email_preview', 'mail', 'mail', null, null, false, null, null, [
                'email' => static fn() => \WP_Settings_Harness::notice_email('S', (string) get_option('wp_settings_harness_notice_body')),
            ]))->init_email_preview();
            $markup = (string) ob_get_clean();
        } finally {
            delete_option('wp_settings_harness_notice_body');
        }

        preg_match('~srcdoc="([^"]*)"~', $markup, $m);
        $this->assertStringContainsString('<p style="margin:0 0 16px;">Saved for Jane Doe</p>', html_entity_decode($m[1] ?? '', ENT_QUOTES, 'UTF-8'));
    }
}
