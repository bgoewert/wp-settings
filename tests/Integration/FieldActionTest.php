<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * Field actions against real WordPress.
 *
 * The unit suite renders through a pass-through wp_kses and a fake nonce; only
 * a real WP says whether the button keeps its `form` attribute and whether the
 * form's nonce verifies for the action it names.
 */
#[Group('integration')]
final class FieldActionTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
        wp_set_current_user(1);
    }

    public function test_the_button_keeps_its_form_attribute_through_kses(): void
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['signing_key']->init_type();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('form="wps-action-wp_settings_harness_signing_key-wp_settings_harness_generate">Generate', $output);
    }

    public function test_the_form_nonce_verifies_for_its_action(): void
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['signing_key']->init_type();
        WP_Setting::render_action_forms();
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/name="_wpnonce" value="([^"]+)"/', $output);
        preg_match('/name="_wpnonce" value="([^"]+)"/', $output, $nonce);
        self::assertNotFalse(wp_verify_nonce($nonce[1], 'wp_settings_harness_generate'));
        self::assertStringContainsString('action="' . esc_url(admin_url('admin-post.php')) . '"', $output);
    }

    public function test_a_rendered_control_keeps_its_data_attributes_through_kses(): void
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['key_status']->init_status();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('<button type="button" class="button" id="wp-settings-harness-check" data-target="wp_settings_harness_key_status">Check</button>', $output);
    }
}
