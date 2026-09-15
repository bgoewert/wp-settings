<?php

declare(strict_types=1);

namespace BGoewert\WP_Settings\Tests\Integration;

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Setting_Encryption;
use PHPUnit\Framework\Attributes\Group;
use Seiler\Test\IntegrationTestCase;

/**
 * The `encrypted` arg against real WordPress.
 *
 * Encryption hangs off the same `sanitize_option_{$option}` filter as every
 * other sanitizer, so it applies to any writer — including one that never goes
 * near this library. Only a real WP has that filter, and the cases that matter
 * are all about a second pass over an already-ciphered value.
 */
#[Group('integration')]
final class EncryptedFieldTest extends IntegrationTestCase
{
    protected bool $runInIntegration = true;

    private const OPTION = 'wp_settings_harness_api_token';

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

    /** The stored value is ciphertext, and only the declared read gives it back. */
    public function test_a_declared_field_is_stored_ciphered(): void
    {
        update_option(self::OPTION, 'sensitive-api-token-12345');

        $stored = get_option(self::OPTION);

        self::assertNotSame('sensitive-api-token-12345', $stored);
        self::assertSame('sensitive-api-token-12345', WP_Setting::try_decrypt($stored));
    }

    /**
     * A resave must not cipher the ciphertext. The fingerprint is what makes
     * that detectable without decrypting first.
     */
    public function test_storing_the_ciphertext_again_does_not_double_encrypt(): void
    {
        update_option(self::OPTION, 'sensitive-api-token-12345');
        $first = get_option(self::OPTION);

        update_option(self::OPTION, $first);

        self::assertSame($first, get_option(self::OPTION));
        self::assertSame('sensitive-api-token-12345', WP_Setting::try_decrypt(get_option(self::OPTION)));
    }

    /** An empty value stays empty rather than becoming ciphertext of nothing. */
    public function test_an_empty_value_is_stored_as_is(): void
    {
        update_option(self::OPTION, '');

        self::assertSame('', get_option(self::OPTION));
    }

    /** A field that never declared `encrypted` is untouched. */
    public function test_an_undeclared_field_is_stored_in_the_clear(): void
    {
        update_option('wp_settings_harness_plain_note', 'not a secret');

        self::assertSame('not a secret', get_option('wp_settings_harness_plain_note'));

        delete_option('wp_settings_harness_plain_note');
    }

    /** Neither the plaintext nor the ciphertext reaches the rendered input. */
    public function test_the_field_renders_no_stored_value(): void
    {
        update_option(self::OPTION, 'sensitive-api-token-12345');

        $html = $this->render();

        self::assertStringNotContainsString('sensitive-api-token-12345', $html);
        self::assertStringNotContainsString(WP_Setting_Encryption::OPENSSL_PREFIX_V2, $html);
        self::assertStringContainsString('Value saved.', $html);
    }

    /**
     * A value written under a key the site no longer resolves renders empty with
     * the key-change notice — the ciphertext is not something an admin can edit,
     * and "check your credentials" would send them to the wrong system.
     */
    public function test_a_value_under_another_key_renders_the_key_change_notice(): void
    {
        $other = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('R', 32), str_repeat('N', 24));
        $this->storeWithoutSanitizing($other->encrypt('sensitive-api-token-12345'));

        $html = $this->render();

        self::assertStringContainsString('encryption key changed', $html);
        self::assertStringNotContainsString('sensitive-api-token-12345', $html);
    }

    /**
     * Render the harness's encrypted field.
     */
    private function render(): string
    {
        ob_start();
        $GLOBALS['wp_settings_harness']->get_settings()['api_token']->init_type();

        return (string) ob_get_clean();
    }

    /**
     * Store a value as a previous release would have left it, bypassing the
     * registered sanitizer — which would otherwise treat foreign ciphertext as
     * a plaintext it has to protect, and encrypt it again.
     */
    private function storeWithoutSanitizing(string $value): void
    {
        global $wp_filter;

        $hook = 'sanitize_option_' . self::OPTION;
        $registered = $wp_filter[$hook] ?? null;
        unset($wp_filter[$hook]);

        update_option(self::OPTION, $value);

        if (null !== $registered) {
            $wp_filter[$hook] = $registered;
        }
    }
}
