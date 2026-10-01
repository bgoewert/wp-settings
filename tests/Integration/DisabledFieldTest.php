<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * The `disabled` arg against real WordPress.
 *
 * options.php writes `null` for a control missing from the POST, through the
 * real `sanitize_option_{$option}` filter; only a real WP says whether that
 * write leaves the stored value alone.
 */
#[Group('integration')]
final class DisabledFieldTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    private const OPTION = 'wp_settings_harness_hide_quotes';

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
        wp_set_current_user(1);
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION);

        parent::tearDown();
    }

    public function test_the_write_options_php_makes_for_a_missing_control_keeps_the_value(): void
    {
        $GLOBALS['wpdb']->replace($GLOBALS['wpdb']->options, array('option_name' => self::OPTION, 'option_value' => '1', 'autoload' => 'off'));
        wp_cache_delete(self::OPTION, 'options');
        wp_cache_delete('alloptions', 'options');

        update_option(self::OPTION, null);
        update_option(self::OPTION, '0');

        self::assertSame('1', get_option(self::OPTION));
    }
}
