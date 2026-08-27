<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * The `dual_list` field type against real WordPress.
 *
 * The unit suite calls the sanitizer directly. What only a real WP has is the
 * `sanitize_option_{$option}` filter `register_setting()` installs — the pass
 * every writer goes through, and where an ordered array either survives or
 * quietly becomes something else.
 */
#[Group('integration')]
final class DualListTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    private const OPTION = 'wp_settings_harness_attendee_columns';

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION);

        parent::tearDown();
    }

    /** The chosen order is stored as submitted, not reordered to match the options. */
    public function test_the_chosen_order_survives_a_write(): void
    {
        update_option(self::OPTION, ['ticket', 'primary_info']);

        self::assertSame(['ticket', 'primary_info'], get_option(self::OPTION));
    }

    /** The sanitizer runs on every writer, so it has to survive its own output. */
    public function test_writing_the_stored_list_again_is_stable(): void
    {
        update_option(self::OPTION, ['email', 'ticket']);
        $stored = get_option(self::OPTION);

        update_option(self::OPTION, $stored);

        self::assertSame(['email', 'ticket'], get_option(self::OPTION));
    }

    /** A key that is not an option cannot be smuggled in through a crafted POST. */
    public function test_a_key_outside_the_options_is_rejected_on_write(): void
    {
        update_option(self::OPTION, ['ticket', 'wp_users_table']);

        self::assertSame(['ticket'], get_option(self::OPTION));
    }

    /**
     * Choosing nothing is a real state. The field posts an empty sentinel so it
     * reaches the sanitizer at all, and what lands is an empty list rather than
     * the previous selection.
     */
    public function test_choosing_nothing_clears_the_option(): void
    {
        update_option(self::OPTION, ['ticket']);

        update_option(self::OPTION, ['']);

        self::assertSame([], get_option(self::OPTION));
    }

    /** The declared default reaches the option row through add_option(). */
    public function test_the_declared_default_is_the_chosen_side(): void
    {
        self::assertSame(['primary_info', 'ticket'], WP_Setting::get('attendee_columns', ['primary_info', 'ticket']));
    }

    /** The stored order is what renders, and the rest of the options are available. */
    public function test_the_stored_order_renders_on_the_chosen_side(): void
    {
        update_option(self::OPTION, ['ticket', 'email']);

        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['attendee_columns']->init_type();
        $output = ob_get_clean();

        $chosen = substr($output, strpos($output, 'data-role="chosen"'));
        self::assertStringContainsString('data-key="ticket"', $chosen);
        self::assertStringContainsString('>Ticket</li><li', $chosen);
        self::assertStringContainsString('data-key="email"', $chosen);
        self::assertStringContainsString('name="' . self::OPTION . '[]" value="ticket"', $output);
    }

    /**
     * The unit suite renders through a pass-through wp_kses stub, so it cannot
     * see an attribute kses drops. Every attribute the listbox pattern needs has
     * to survive the real filter, or the field renders as an inert <ul> (#23).
     */
    public function test_the_listbox_attributes_survive_kses(): void
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['attendee_columns']->init_type();
        $output = ob_get_clean();

        foreach (
            [
                'role="listbox"',
                'aria-multiselectable="true"',
                'aria-labelledby="' . self::OPTION . '_chosen_label"',
                'aria-describedby="' . self::OPTION . '_help"',
                'tabindex="0"',
                'style="height:14.4em"',
                'role="option"',
                'draggable="true"',
                'aria-selected="false"',
            ] as $attribute
        ) {
            self::assertStringContainsString($attribute, $output);
        }
    }
}
