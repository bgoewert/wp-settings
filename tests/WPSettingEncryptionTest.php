<?php

use BGoewert\WP_Settings\WP_Setting_Encryption;

/**
 * Tests for WP_Setting_Encryption class
 * 
 * These tests expose two critical bugs:
 * 1. Greedy regex captures closing `');` when reading constants from wp-config.php
 * 2. FILE_APPEND places auto-generated constants AFTER `require_once wp-settings.php`
 */
class WPSettingEncryptionTest extends WP_Settings_TestCase
{
    private $original_config_content;
    private $config_file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config_file = ABSPATH . 'wp-config.php';
        // Save original config content for restoration
        if (file_exists($this->config_file)) {
            $this->original_config_content = file_get_contents($this->config_file);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Restore original config content
        if (isset($this->original_config_content)) {
            file_put_contents($this->config_file, $this->original_config_content);
        }
    }

    /**
     * BUG 1: Test that key is read correctly from config file
     * 
     * FAILS against current code because greedy regex `[\w\W\d]{N,}` captures
     * the closing `');` when reading the constant value, causing base64_decode
     * to fail and wrong key bytes to be used.
     */
    public function test_key_read_from_config_file_decodes_correctly(): void
    {
        // Create a temp wp-config with a known key
        $test_key_value = 'K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=';
        $test_nonce_value = 'GwoEWSFBbok/aT+eOF0ZckCnZBAU04MW';
        
        $config_content = <<<'PHP'
<?php
define('TEST_ENC_KEY_BUG1', 'K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=');
define('TEST_ENC_NONCE_BUG1', 'GwoEWSFBbok/aT+eOF0ZckCnZBAU04MW');
require_once ABSPATH . 'wp-settings.php';
PHP;

        file_put_contents($this->config_file, $config_content);

        // Instantiate encryption with test constants
        $crypt = new WP_Setting_Encryption('TEST_ENC_KEY_BUG1', 'TEST_ENC_NONCE_BUG1');

        // Use Reflection to read the private $key property
        $reflection = new ReflectionClass($crypt);
        $key_property = $reflection->getProperty('key');
        $key_property->setAccessible(true);
        $actual_key = $key_property->getValue($crypt);

        // The expected key is the base64-decoded value
        $expected_key = base64_decode($test_key_value);

        // This assertion FAILS with current code because the regex captures `=');`
        // causing base64_decode to fail, and the raw polluted string is used instead
        $this->assertSame($expected_key, $actual_key, 
            'Key should be correctly decoded from base64 in wp-config.php');
    }

    /**
     * BUG 1: Test encrypt/decrypt roundtrip with keys read from config file
     * 
     * FAILS against current code because the greedy regex corrupts the key value,
     * causing sodium_crypto_secretbox_open to fail and return an Error object.
     */
    public function test_encrypt_decrypt_roundtrip_with_config_file_keys(): void
    {
        // Create a temp wp-config with known key/nonce
        $config_content = <<<'PHP'
<?php
define('TEST_ENC_KEY_ROUNDTRIP', 'K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=');
define('TEST_ENC_NONCE_ROUNDTRIP', 'GwoEWSFBbok/aT+eOF0ZckCnZBAU04MW');
require_once ABSPATH . 'wp-settings.php';
PHP;

        file_put_contents($this->config_file, $config_content);

        // Instantiate encryption with test constants
        $crypt = new WP_Setting_Encryption('TEST_ENC_KEY_ROUNDTRIP', 'TEST_ENC_NONCE_ROUNDTRIP');

        // Encrypt a test value
        $plaintext = 'my-secret-value';
        $encrypted = $crypt->encrypt($plaintext);

        // Verify encryption succeeded (should be a string, not an Error)
        $this->assertIsString($encrypted, 'Encryption should succeed and return a string');

        // Decrypt and verify roundtrip
        $decrypted = $crypt->decrypt($encrypted);

        // This assertion FAILS with current code because wrong key bytes cause
        // sodium_crypto_secretbox_open to return false, which becomes an Error object
        $this->assertSame($plaintext, $decrypted,
            'Decryption should return original plaintext when using correct key from config');
    }

    /**
     * BUG 2: Test that generated constant is inserted BEFORE require_once wp-settings.php
     * 
     * FAILS against current code because FILE_APPEND places the define AFTER
     * the require_once, making it unavailable during WordPress execution.
     */
    public function test_generated_constant_inserted_before_wp_settings_require(): void
    {
        // Create a minimal wp-config with only require_once
        $config_content = <<<'PHP'
<?php
// WordPress config
require_once ABSPATH . 'wp-settings.php';
PHP;

        file_put_contents($this->config_file, $config_content);

        // Instantiate encryption with a non-existent constant
        // This triggers the auto-generation path
        $crypt = new WP_Setting_Encryption('NONEXISTENT_KEY_ABCXYZ_BUG2', 'NONEXISTENT_NONCE_ABCXYZ_BUG2');

        // Read the updated config file
        $updated_content = file_get_contents($this->config_file);

        // Find positions of the define and require_once
        $define_pos = strpos($updated_content, "define('NONEXISTENT_KEY_ABCXYZ_BUG2'");
        $require_pos = strpos($updated_content, "require_once ABSPATH . 'wp-settings.php'");

        // Both should exist
        $this->assertNotFalse($define_pos, 'Generated constant should be in wp-config.php');
        $this->assertNotFalse($require_pos, 'require_once should still be in wp-config.php');

        // The define MUST come BEFORE require_once
        // This assertion FAILS with current code because FILE_APPEND puts it AFTER
        $this->assertLessThan($require_pos, $define_pos,
            'Generated constant must be inserted BEFORE require_once wp-settings.php');
    }

    /**
     * BUG 2: Test that generated constant is appended when no require_once exists
     * 
     * This test PASSES against current code because FILE_APPEND works correctly
     * when there's no require_once to worry about.
     */
    public function test_generated_constant_appended_when_no_wp_settings_require(): void
    {
        // Create a minimal wp-config WITHOUT require_once
        $config_content = <<<'PHP'
<?php
// Non-standard config
PHP;

        file_put_contents($this->config_file, $config_content);

        // Instantiate encryption with a non-existent constant
        $crypt = new WP_Setting_Encryption('NONEXISTENT_KEY_FALLBACK_BUG2', 'NONEXISTENT_NONCE_FALLBACK_BUG2');

        // Read the updated config file
        $updated_content = file_get_contents($this->config_file);

        // The define should be present (appended)
        $this->assertStringContainsString("define('NONEXISTENT_KEY_FALLBACK_BUG2'", $updated_content,
            'Generated constant should be appended to wp-config.php when no require_once exists');
    }

    /**
     * BUG 1 + BUG 2: Full roundtrip with keys defined AFTER require_once
     * 
     * FAILS against current code due to Bug 1 (greedy regex corrupts key).
     * Even if Bug 2 were fixed, this would still fail because of Bug 1.
     */
    public function test_full_roundtrip_with_keys_after_wp_settings_require(): void
    {
        // Create a wp-config with keys defined AFTER require_once
        // (This is the broken state that Bug 2 creates)
        $config_content = <<<'PHP'
<?php
require_once ABSPATH . 'wp-settings.php';
define('TEST_LATE_KEY_BUG12', 'K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=');
define('TEST_LATE_NONCE_BUG12', 'GwoEWSFBbok/aT+eOF0ZckCnZBAU04MW');
PHP;

        file_put_contents($this->config_file, $config_content);

        // Instantiate encryption
        $crypt = new WP_Setting_Encryption('TEST_LATE_KEY_BUG12', 'TEST_LATE_NONCE_BUG12');

        // Try to encrypt and decrypt
        $plaintext = 'test-value';
        $encrypted = $crypt->encrypt($plaintext);

        // Verify encryption succeeded
        $this->assertIsString($encrypted, 'Encryption should succeed');

        // Decrypt
        $decrypted = $crypt->decrypt($encrypted);

        // This assertion FAILS with current code because Bug 1 (greedy regex)
        // corrupts the key value read from the file
        $this->assertSame($plaintext, $decrypted,
            'Roundtrip should work with keys read from config file');
    }

    /**
     * Test that safe_base64_decode handles valid base64 correctly
     * 
     * This is a helper test to verify the safe_base64_decode method works
     * as expected for valid base64 strings.
     */
    public function test_safe_base64_decode_handles_valid_base64(): void
    {
        // Use reflection to call the private safe_base64_decode method
        $reflection = new ReflectionClass(WP_Setting_Encryption::class);
        $method = $reflection->getMethod('safe_base64_decode');
        $method->setAccessible(true);

        // Test with valid base64
        $original = 'K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=';
        $decoded = $method->invoke(null, $original);
        $expected = base64_decode($original);

        $this->assertSame($expected, $decoded,
            'safe_base64_decode should correctly decode valid base64');
    }

    /**
     * Test that safe_base64_decode returns original for invalid base64
     * 
     * This verifies the fallback behavior when base64_decode fails.
     */
    public function test_safe_base64_decode_returns_original_for_invalid(): void
    {
        // Use reflection to call the private safe_base64_decode method
        $reflection = new ReflectionClass(WP_Setting_Encryption::class);
        $method = $reflection->getMethod('safe_base64_decode');
        $method->setAccessible(true);

        // Test with invalid base64 (contains closing punctuation)
        $invalid = "K8t+FzSc1D/rL4xgHIrGHMXIT8dhvNzMeeX7njFNe2k=');";
        $result = $method->invoke(null, $invalid);

        // Should return the original string since it's not valid base64
        $this->assertSame($invalid, $result,
            'safe_base64_decode should return original for invalid base64');
    }

    /**
     * Test that encryption returns Error when sodium extension is missing
     * 
     * This is a sanity check for the error handling in encrypt().
     */
    public function test_encrypt_returns_error_without_sodium(): void
    {
        // This test assumes sodium IS loaded (which it should be in the test environment)
        // If sodium is not loaded, this test would verify the error handling
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        // Create a simple encryption instance
        $crypt = new WP_Setting_Encryption('TEST_KEY', 'TEST_NONCE');

        // Encrypt should succeed with sodium loaded
        $result = $crypt->encrypt('test');
        $this->assertIsString($result, 'Encryption should return string when sodium is loaded');
    }

    /**
     * Test that decryption throws for truncated ciphertext
     *
     * This verifies error handling for malformed encrypted data.
     */
    public function test_decrypt_throws_for_truncated_ciphertext(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_KEY', 'TEST_NONCE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('truncated');

        // Try to decrypt a truncated base64 string
        $crypt->decrypt('dGVzdA=='); // Just "test" in base64, too short
    }

    /**
     * Test that decryption throws for tampered ciphertext
     *
     * This verifies that sodium_crypto_secretbox_open detects tampering.
     */
    public function test_decrypt_throws_for_tampered_ciphertext(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_KEY', 'TEST_NONCE');

        // Encrypt something
        $encrypted = $crypt->encrypt('test');

        // Tamper with the encrypted data by flipping a bit
        $decoded = base64_decode($encrypted);
        $tampered = $decoded[0] === 'a' ? 'b' . substr($decoded, 1) : 'a' . substr($decoded, 1);
        $tampered_encrypted = base64_encode($tampered);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tampered');

        // Try to decrypt the tampered data
        $crypt->decrypt($tampered_encrypted);
    }

    /**
     * The class must be constructable on PHP without sodium.
     *
     * Regression test for #12: the lengths were property initialisers reading
     * SODIUM_* constants, so instantiation fataled with an undefined-constant
     * \Error before any extension_loaded() guard could run.
     */
    public function test_lengths_are_not_read_from_sodium_constants_at_class_load(): void
    {
        $reflection = new ReflectionClass(WP_Setting_Encryption::class);

        foreach (['key_length', 'nonce_length', 'mac_length'] as $name) {
            $property = $reflection->getProperty($name);
            $this->assertFalse($property->hasDefaultValue() && $property->getDefaultValue() !== null,
                sprintf('%s must be resolved in the constructor, not from a SODIUM_* property initialiser', $name));
        }

        $crypt = new WP_Setting_Encryption('TEST_LEN_KEY', 'TEST_LEN_NONCE');

        foreach (['key_length' => 32, 'nonce_length' => 24, 'mac_length' => 16] as $name => $expected) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $this->assertSame($expected, $property->getValue($crypt),
                sprintf('%s should resolve to %d', $name, $expected));
        }
    }

    /**
     * The openssl fallback must round-trip on its own.
     */
    public function test_openssl_fallback_roundtrip(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_OSSL_KEY', 'TEST_OSSL_NONCE');
        $plaintext = 'sensitive-api-token-12345';

        $reflection = new ReflectionClass($crypt);
        $encrypt = $reflection->getMethod('openssl_encrypt');
        $encrypt->setAccessible(true);

        $encrypted = $encrypt->invoke($crypt, $plaintext);

        $this->assertStringStartsWith(WP_Setting_Encryption::OPENSSL_PREFIX, $encrypted,
            'openssl payloads must carry the format marker so decrypt() can dispatch on it');
        $this->assertNotSame($plaintext, $encrypted);
        $this->assertSame($plaintext, $crypt->decrypt($encrypted),
            'decrypt() should route a prefixed payload to the openssl path');
    }

    /**
     * End-to-end regression test for #12 on a simulated sodium-less build:
     * constructing must not fatal, and encrypt() must fall back to openssl.
     */
    public function test_encrypt_falls_back_to_openssl_when_sodium_is_missing(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension not loaded');
        }

        $plaintext = 'sensitive-api-token-12345';

        [$encrypted, $decrypted] = wp_settings_test_without_extension('sodium', function () use ($plaintext) {
            $crypt = new WP_Setting_Encryption('TEST_NOSODIUM_KEY', 'TEST_NOSODIUM_NONCE');
            $encrypted = $crypt->encrypt($plaintext);
            return [$encrypted, $crypt->decrypt($encrypted)];
        });

        $this->assertStringStartsWith(WP_Setting_Encryption::OPENSSL_PREFIX, $encrypted,
            'Without sodium, encrypt() should produce an openssl payload');
        $this->assertSame($plaintext, $decrypted);

        // The payload stays readable once sodium is back — the prefix pins the format.
        $crypt = new WP_Setting_Encryption('TEST_NOSODIUM_KEY', 'TEST_NOSODIUM_NONCE');
        $this->assertSame($plaintext, $crypt->decrypt($encrypted),
            'openssl payloads must remain readable on a build that also has sodium');
    }

    /**
     * With neither extension, encryption throws a catchable exception rather
     * than an \Error that escapes catch (\Exception).
     */
    public function test_encrypt_throws_catchable_exception_without_any_backend(): void
    {
        $caught = wp_settings_test_without_extension('sodium', function () {
            return wp_settings_test_without_extension('openssl', function () {
                $this->assertFalse(WP_Setting_Encryption::is_available(),
                    'is_available() should report false when neither backend is present');

                $crypt = new WP_Setting_Encryption('TEST_NOEXT_KEY', 'TEST_NOEXT_NONCE');

                try {
                    $crypt->encrypt('test');
                } catch (\Exception $e) {
                    return $e;
                }

                return null;
            });
        });

        $this->assertInstanceOf(\Exception::class, $caught,
            'encrypt() should throw an \Exception subclass so callers catching \Exception still degrade');
        $this->assertTrue(WP_Setting_Encryption::is_available(),
            'is_available() should report true again once the stub is lifted');
    }

    /**
     * A sodium payload cannot be read without sodium, but the failure must be a
     * thrown exception rather than a fatal.
     */
    public function test_decrypt_throws_for_sodium_payload_without_sodium(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_LEGACY_KEY', 'TEST_LEGACY_NONCE');
        $encrypted = $crypt->encrypt('sensitive-api-token-12345');

        wp_settings_test_without_extension('sodium', function () use ($encrypted) {
            $crypt = new WP_Setting_Encryption('TEST_LEGACY_KEY', 'TEST_LEGACY_NONCE');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('sodium extension is not loaded');

            $crypt->decrypt($encrypted);
        });
    }

    /**
     * A fresh IV per call — GCM IV reuse across values would be catastrophic.
     */
    public function test_openssl_fallback_uses_a_fresh_iv_per_call(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_OSSL_KEY', 'TEST_OSSL_NONCE');

        $reflection = new ReflectionClass($crypt);
        $encrypt = $reflection->getMethod('openssl_encrypt');
        $encrypt->setAccessible(true);

        $this->assertNotSame(
            $encrypt->invoke($crypt, 'same-plaintext'),
            $encrypt->invoke($crypt, 'same-plaintext'),
            'Encrypting the same value twice must not produce the same ciphertext'
        );
    }

    /**
     * A tampered openssl payload must fail authentication, not decrypt to garbage.
     */
    public function test_openssl_fallback_rejects_tampered_payload(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_OSSL_KEY', 'TEST_OSSL_NONCE');

        $reflection = new ReflectionClass($crypt);
        $encrypt = $reflection->getMethod('openssl_encrypt');
        $encrypt->setAccessible(true);

        $encrypted = $encrypt->invoke($crypt, 'sensitive-api-token-12345');
        $payload = base64_decode(substr($encrypted, strlen(WP_Setting_Encryption::OPENSSL_PREFIX)));
        // Flip a bit in the ciphertext body, past the IV and tag.
        $offset = WP_Setting_Encryption::OPENSSL_IV_LENGTH + WP_Setting_Encryption::DEFAULT_MAC_LENGTH;
        $payload[$offset] = chr(ord($payload[$offset]) ^ 0x01);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tampered');

        $crypt->decrypt(WP_Setting_Encryption::OPENSSL_PREFIX . base64_encode($payload));
    }

    /**
     * An empty value round-trips to an empty string without touching either backend.
     */
    public function test_decrypt_returns_empty_string_for_empty_input(): void
    {
        $crypt = new WP_Setting_Encryption('TEST_EMPTY_KEY', 'TEST_EMPTY_NONCE');

        $this->assertSame('', $crypt->decrypt(''));
        $this->assertSame('', $crypt->decrypt(null));
    }

    /**
     * WP_Setting::encrypt()/decrypt() must degrade to the original value rather
     * than let a \Throwable escape — \Error is not an \Exception, so the old
     * catch (\Exception) let undefined-constant fatals through.
     */
    public function test_wp_setting_wrappers_return_original_value_on_failure(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $reflection = new ReflectionClass(\BGoewert\WP_Settings\WP_Setting::class);
        $text_domain = $reflection->getProperty('text_domain');
        $text_domain->setAccessible(true);
        $text_domain->setValue(null, 'test-plugin');

        // A value that is neither valid sodium nor openssl ciphertext: decrypt()
        // throws internally, and the wrapper must hand back what it was given.
        $garbage = 'not-actually-encrypted';

        $this->assertSame($garbage, @\BGoewert\WP_Settings\WP_Setting::decrypt($garbage),
            'decrypt() should return the original value when decryption fails');
    }

    /**
     * Run $callback with a PHP-level error handler that records every E_DEPRECATED/E_WARNING
     * raised during its execution, instead of letting them pass through silently.
     *
     * @return array{0: mixed, 1: string[]} The callback's return value and any captured notices.
     */
    private function captureDeprecations(callable $callback): array
    {
        $notices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$notices): bool {
            $notices[] = $errstr;
            return true;
        }, E_DEPRECATED | E_WARNING);

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $notices];
    }

    /**
     * Regression test for the mb_strlen(null) deprecation (2.28.2 regression).
     *
     * A key/nonce constant defined as `null` (e.g. `define('X_KEY', getenv('X_KEY') ?: null);`,
     * a common pattern for "not configured yet") used to flow unmodified through
     * safe_base64_decode() into check_key_len()/check_nonce_len(), reaching mb_strlen() as
     * null and emitting a PHP deprecation notice.
     */
    public function test_get_default_key_and_nonce_are_strings_when_constant_defined_as_null(): void
    {
        if (!defined('TEST_ENC_KEY_NULLVAL')) {
            define('TEST_ENC_KEY_NULLVAL', null);
        }
        if (!defined('TEST_ENC_NONCE_NULLVAL')) {
            define('TEST_ENC_NONCE_NULLVAL', null);
        }

        [$crypt, $notices] = $this->captureDeprecations(
            fn() => new WP_Setting_Encryption('TEST_ENC_KEY_NULLVAL', 'TEST_ENC_NONCE_NULLVAL')
        );

        $this->assertSame([], $notices,
            'Instantiating with a null-valued key/nonce constant must not raise a PHP deprecation notice.');

        $reflection = new ReflectionClass($crypt);
        $key_property = $reflection->getProperty('key');
        $key_property->setAccessible(true);
        $nonce_property = $reflection->getProperty('nonce');
        $nonce_property->setAccessible(true);

        $this->assertIsString($key_property->getValue($crypt),
            'get_default_key() must return a string, never null.');
        $this->assertIsString($nonce_property->getValue($crypt),
            'get_default_nonce() must return a string, never null.');
    }

    /**
     * Regression test: no key/nonce constant, no wp-config.php stored fallback option
     * available (config file missing entirely). Documents that get_default_key()/
     * get_default_nonce() still resolve to a string via the LOGGED_IN_KEY/hardcoded
     * fallback paths, with no deprecation notice.
     */
    public function test_get_default_key_and_nonce_are_strings_when_nothing_is_configured(): void
    {
        if (file_exists($this->config_file)) {
            unlink($this->config_file);
        }

        [$crypt, $notices] = $this->captureDeprecations(
            fn() => new WP_Setting_Encryption('TEST_ENC_KEY_MISSING', 'TEST_ENC_NONCE_MISSING')
        );

        $this->assertSame([], $notices,
            'Instantiating with no config file and no key/nonce constants defined must not raise a PHP deprecation notice.');

        $reflection = new ReflectionClass($crypt);
        $key_property = $reflection->getProperty('key');
        $key_property->setAccessible(true);
        $nonce_property = $reflection->getProperty('nonce');
        $nonce_property->setAccessible(true);

        $this->assertIsString($key_property->getValue($crypt),
            'get_default_key() must return a string even when nothing is configured.');
        $this->assertIsString($nonce_property->getValue($crypt),
            'get_default_nonce() must return a string even when nothing is configured.');
    }

    /**
     * Regression test for the PHP 8.1+ mb_strlen(null) / base64_decode(null) deprecation
     * emitted downstream by v2.29.0 (GitHub issue #4).
     *
     * Rather than proving every caller resolves to a string, the fix guards the leaf
     * helpers themselves so a null can never reach mb_strlen()/base64_decode(). This
     * exercises those helpers directly with null and asserts they stay silent and
     * return a string. Fails against unguarded code (deprecation notice raised).
     */
    public function test_leaf_helpers_coerce_null_without_deprecation(): void
    {
        $crypt = new WP_Setting_Encryption('TEST_ENC_KEY_LEAF', 'TEST_ENC_NONCE_LEAF');
        $reflection = new ReflectionClass($crypt);

        foreach (['check_key_len', 'check_nonce_len', 'safe_base64_decode'] as $name) {
            $method = $reflection->getMethod($name);
            $method->setAccessible(true);

            // safe_base64_decode is static; check_*_len are instance methods.
            $target = $method->isStatic() ? null : $crypt;

            [$result, $notices] = $this->captureDeprecations(
                fn() => $method->invoke($target, null)
            );

            $this->assertSame([], $notices,
                "{$name}(null) must not raise a PHP deprecation notice.");
            $this->assertIsString($result,
                "{$name}(null) must return a string, never null.");
        }
    }
}
