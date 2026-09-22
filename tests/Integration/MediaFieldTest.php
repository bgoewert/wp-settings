<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * The `media` field type against real WordPress.
 *
 * The unit suite resolves attachments through stubs and renders through a
 * pass-through wp_kses. Only a real WP says whether a deleted attachment stops
 * resolving and whether the preview survives the kses filter.
 */
#[Group('integration')]
final class MediaFieldTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    private const OPTION = 'wp_settings_harness_site_logo';

    /** @var int[] */
    private array $attachments = [];

    protected function setUp(): void
    {
        parent::setUp();

        WP_Setting::$text_domain = 'wp_settings_harness';
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION);
        foreach ($this->attachments as $id) {
            wp_delete_attachment($id, true);
        }

        parent::tearDown();
    }

    private function attachment(string $file, string $mime, string $alt = ''): int
    {
        $id = wp_insert_attachment(
            ['post_title' => 'Harness ' . $file, 'post_mime_type' => $mime, 'post_status' => 'inherit'],
            $file
        );
        self::assertIsInt($id);
        if ('' !== $alt) {
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
        }
        $this->attachments[] = $id;

        return $id;
    }

    private function render(): string
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['site_logo']->init_type();

        return (string) ob_get_clean();
    }

    /** Every writer goes through the registered sanitizer, and an image id survives it. */
    public function test_an_image_id_is_stored(): void
    {
        $id = $this->attachment('harness-logo.png', 'image/png');

        update_option(self::OPTION, (string) $id);

        self::assertSame($id, get_option(self::OPTION));
    }

    public function test_an_id_that_is_not_an_attachment_is_rejected(): void
    {
        $post = wp_insert_post(['post_title' => 'Not media', 'post_status' => 'draft']);
        $this->attachments[] = $post;

        update_option(self::OPTION, $post);

        self::assertSame('', get_option(self::OPTION));
    }

    public function test_a_file_outside_the_accepted_types_is_rejected(): void
    {
        $id = $this->attachment('harness-sheet.pdf', 'application/pdf');

        update_option(self::OPTION, $id);

        self::assertSame('', get_option(self::OPTION));
    }

    /** A deleted attachment renders as no image, so the next save clears it. */
    public function test_a_deleted_attachment_renders_as_no_choice(): void
    {
        $id = $this->attachment('harness-logo.png', 'image/png');
        update_option(self::OPTION, $id);
        wp_delete_attachment($id, true);

        $output = $this->render();

        self::assertStringContainsString('name="' . self::OPTION . '" id="' . self::OPTION . '" value=""', $output);
        self::assertStringNotContainsString('<img', $output);
        self::assertSame('', WP_Setting::get_attachment_url('site_logo'));
    }

    /** The preview and the script's data attributes have to survive the real kses filter. */
    public function test_the_preview_survives_kses(): void
    {
        $id = $this->attachment('harness-logo.png', 'image/png', 'Harness wordmark');
        update_option(self::OPTION, $id);

        $output = $this->render();

        self::assertMatchesRegularExpression('/<img class="wps-media-image" src="[^"]+harness-logo\.png" alt="Harness wordmark"/', $output);
        self::assertStringContainsString('data-mime-types="[&quot;image&quot;]"', $output);
        self::assertStringContainsString('data-size="thumbnail"', $output);
        self::assertStringContainsString('data-frame-title="Choose Site Logo"', $output);
        self::assertStringContainsString('class="button wps-media-remove hide-if-no-js">Remove', $output);
    }

    public function test_an_empty_field_hides_remove(): void
    {
        self::assertMatchesRegularExpression('/class="button wps-media-remove hide-if-no-js" hidden(="[^"]*")?>/', $this->render());
    }

    public function test_get_attachment_url_reads_the_stored_id(): void
    {
        $id = $this->attachment('harness-logo.png', 'image/png');
        update_option(self::OPTION, $id);

        self::assertSame(wp_get_attachment_url($id), WP_Setting::get_attachment_url('site_logo'));
    }
}
