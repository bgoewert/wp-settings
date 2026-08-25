<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * The `delimiter` arg against real WordPress.
 *
 * The unit suite calls the sanitizer directly. Everything that made #20 a
 * production bug happened somewhere else: `register_setting()` hangs the
 * sanitizer on `sanitize_option_{$option}`, and `update_option()` applies it to
 * every writer — including ones that never go near this library. Only a real WP
 * has that filter.
 */
#[Group('integration')]
final class DelimitedListTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    private const TEXT_OPTION     = 'wp_settings_harness_rental_tags';
    private const TEXTAREA_OPTION = 'wp_settings_harness_allowed_domains';
    private const PLAIN_OPTION    = 'wp_settings_harness_plain_note';

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
    }

    protected function tearDown(): void
    {
        delete_option(self::TEXT_OPTION);
        delete_option(self::TEXTAREA_OPTION);
        delete_option(self::PLAIN_OPTION);

        parent::tearDown();
    }

    /** The registered sanitizer runs on update_option(), so the string arrives split. */
    public function test_a_delimited_string_is_stored_as_a_list(): void
    {
        update_option(self::TEXT_OPTION, 'rental, demo, hire');

        self::assertSame(['rental', 'demo', 'hire'], get_option(self::TEXT_OPTION));
    }

    /**
     * The bug behind #20: the sanitizer runs on every writer, so storing the
     * array it just produced had to survive a second pass. It did not, and the
     * option came back ''.
     */
    public function test_storing_the_list_again_does_not_empty_the_option(): void
    {
        update_option(self::TEXT_OPTION, 'rental, demo');
        $stored = get_option(self::TEXT_OPTION);

        update_option(self::TEXT_OPTION, $stored);

        self::assertSame(['rental', 'demo'], get_option(self::TEXT_OPTION));
    }

    /**
     * WP_Setting::set() writes through update_option(), so it gets the same
     * treatment. The value differs from the declared default deliberately:
     * update_option() is a no-op when the two match, and there would be no row
     * to prove anything about.
     */
    public function test_set_and_get_round_trip_a_list(): void
    {
        WP_Setting::set('rental_tags', ['hire', 'loan']);

        self::assertSame(['hire', 'loan'], WP_Setting::get('rental_tags'));
    }

    /** A string handed to set() is split on the way in, not stored verbatim. */
    public function test_set_splits_a_string_written_through_the_library(): void
    {
        WP_Setting::set('rental_tags', 'hire, loan');

        self::assertSame(['hire', 'loan'], WP_Setting::get('rental_tags'));
    }

    /** A writer that never touches this library still lands the right shape. */
    public function test_a_third_party_writer_gets_the_same_shape(): void
    {
        add_option(self::TEXT_OPTION, 'a,b');

        self::assertSame(['a', 'b'], get_option(self::TEXT_OPTION));
    }

    /** A newline delimiter is what a textarea submits, one item per line. */
    public function test_a_textarea_delimiter_splits_on_lines(): void
    {
        update_option(self::TEXTAREA_OPTION, "example.com\r\nexample.org\r\n");

        self::assertSame(['example.com', 'example.org'], get_option(self::TEXTAREA_OPTION));
    }

    /** The array shape is opt-in: a field without the arg is still a string. */
    public function test_a_field_without_a_delimiter_is_untouched(): void
    {
        update_option(self::PLAIN_OPTION, 'rental, demo');

        self::assertSame('rental, demo', get_option(self::PLAIN_OPTION));
    }

    /** The default reaches the option row through add_option(), already a list. */
    public function test_the_declared_default_is_seeded_as_a_list(): void
    {
        delete_option(self::TEXT_OPTION);

        self::assertSame(['rental', 'demo'], get_option(self::TEXT_OPTION, ['rental', 'demo']));
        self::assertSame(['rental', 'demo'], WP_Setting::get('rental_tags', ['rental', 'demo']));
    }

    /** The stored list renders back into one input, joined the way an admin types it. */
    public function test_the_stored_list_renders_back_into_the_input(): void
    {
        update_option(self::TEXT_OPTION, 'rental, demo');

        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['rental_tags']->init_type();
        $output = ob_get_clean();

        self::assertStringContainsString('value="rental, demo"', $output);
    }
}
