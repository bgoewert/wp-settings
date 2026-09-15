<?php

use BGoewert\WP_Settings\WP_Setting_Encryption;

/**
 * Tests for WP_Setting_Encryption class
 *
 * Covers key resolution, the three ciphertext formats, and the key fingerprint
 * that tells a rotated key apart from a corrupt value.
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
     * Read the resolved key or nonce out of an instance.
     */
    private function resolved(WP_Setting_Encryption $crypt, string $property): string
    {
        $reflection = new ReflectionClass($crypt);
        $value = $reflection->getProperty($property);
        $value->setAccessible(true);

        return (string) $value->getValue($crypt);
    }

    /**
     * A defined constant outranks every other source.
     */
    public function test_a_defined_constant_supplies_the_key(): void
    {
        $key = base64_encode(str_repeat('c', 32));
        define('WPS_DEFINED_KEY', $key);

        $crypt = new WP_Setting_Encryption('WPS_DEFINED_KEY', 'WPS_DEFINED_NONCE');

        $this->assertSame(base64_decode($key), $this->resolved($crypt, 'key'));
        $this->assertSame('constant', $crypt->key_source());
    }

    /**
     * An environment variable of the same name covers .env, Docker and hosting
     * panels, where a constant cannot be defined without editing a config file.
     */
    public function test_an_environment_variable_supplies_the_key_when_no_constant_is_defined(): void
    {
        $key = base64_encode(str_repeat('k', 32));
        putenv('WPS_ENV_ONLY_KEY=' . $key);

        try {
            $crypt = new WP_Setting_Encryption('WPS_ENV_ONLY_KEY', 'WPS_ENV_ONLY_NONCE');

            $this->assertSame(base64_decode($key), $this->resolved($crypt, 'key'));
            $this->assertSame('env', $crypt->key_source());
        } finally {
            putenv('WPS_ENV_ONLY_KEY');
        }
    }

    /**
     * The salts are the documented default: they need no provisioning and exist
     * on every install.
     */
    public function test_the_salts_supply_the_key_when_nothing_else_does(): void
    {
        $crypt = new WP_Setting_Encryption('WPS_ABSENT_KEY', 'WPS_ABSENT_NONCE');

        $this->assertSame(defined('LOGGED_IN_KEY') ? 'salt' : 'fallback', $crypt->key_source());
        $this->assertNotSame('', $this->resolved($crypt, 'key'));
    }

    /**
     * A constant that exists only as unexecuted text in wp-config.php is not a
     * defined constant, and the library no longer reads the file to find it.
     */
    public function test_a_constant_only_present_as_config_text_is_ignored(): void
    {
        $this->assertStringContainsString("define('MY_UNEXECUTED_KEY'", $this->write_config(
            "<?php\ndefine('MY_UNEXECUTED_KEY', 'AAAA');\nrequire_once ABSPATH . 'wp-settings.php';"
        ));

        $crypt = new WP_Setting_Encryption('MY_UNEXECUTED_KEY', 'MY_UNEXECUTED_NONCE');

        $this->assertNotSame('constant', $crypt->key_source());
        $this->assertNotSame('AAAA', $this->resolved($crypt, 'key'));
    }

    /**
     * The library writes no config file, even when it could: a regex rewrite of
     * wp-config.php does not survive an atomic deploy and surprises every host.
     */
    public function test_a_writable_config_file_is_never_modified(): void
    {
        $before = $this->write_config("<?php\nrequire_once ABSPATH . 'wp-settings.php';");
        $this->assertTrue(is_writable($this->config_file), 'The fixture config must be writable for this test to mean anything');

        new WP_Setting_Encryption('WPS_UNPROVISIONED_KEY', 'WPS_UNPROVISIONED_NONCE');

        $this->assertSame($before, file_get_contents($this->config_file));
    }

    /**
     * Write fixture config content and hand it back for comparison.
     */
    private function write_config(string $content): string
    {
        file_put_contents($this->config_file, $content);

        return $content;
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

        // Encrypt down the sodium path explicitly: encrypt() prefers openssl,
        // and this test is about secretbox's own authentication.
        $encrypted = $this->invokeBackend($crypt, 'sodium_encrypt', 'test');

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
     * Drive one backend directly, bypassing encrypt()'s preference order.
     *
     * @param WP_Setting_Encryption $crypt  Instance under test.
     * @param string                $method sodium_encrypt or openssl_encrypt.
     * @param string                $value  Plaintext.
     * @return string The ciphertext in that backend's format.
     */
    private function invokeBackend(WP_Setting_Encryption $crypt, string $method, string $value): string
    {
        $reflection = new ReflectionClass($crypt);
        $backend = $reflection->getMethod($method);
        $backend->setAccessible(true);

        return $backend->invoke($crypt, $value);
    }

    /**
     * openssl is the default writer as of 3.1.0 — it is the more widely built
     * extension, and its path derives a fresh IV rather than reusing the nonce.
     */
    public function test_encrypt_prefers_openssl_when_both_extensions_are_present(): void
    {
        if (!extension_loaded('openssl') || !extension_loaded('sodium')) {
            $this->markTestSkipped('Both openssl and sodium must be loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_PREF_KEY', 'TEST_PREF_NONCE');
        $encrypted = $crypt->encrypt('sensitive-api-token-12345');

        $this->assertStringStartsWith(WP_Setting_Encryption::OPENSSL_PREFIX_V2, $encrypted,
            'encrypt() should write an openssl payload even when sodium is available');
        $this->assertSame('sensitive-api-token-12345', $crypt->decrypt($encrypted));
    }

    /**
     * Sodium still covers a host that has it but not openssl.
     */
    public function test_encrypt_falls_back_to_sodium_when_openssl_is_missing(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $plaintext = 'sensitive-api-token-12345';

        [$encrypted, $decrypted] = wp_settings_test_without_extension('openssl', function () use ($plaintext) {
            $crypt = new WP_Setting_Encryption('TEST_NOSSL_KEY', 'TEST_NOSSL_NONCE');
            $encrypted = $crypt->encrypt($plaintext);
            return [$encrypted, $crypt->decrypt($encrypted)];
        });

        $this->assertStringStartsNotWith(WP_Setting_Encryption::OPENSSL_PREFIX, $encrypted,
            'Without openssl, encrypt() should write a sodium payload');
        $this->assertSame($plaintext, $decrypted);
    }

    /**
     * A payload written by 3.0.x and earlier must still decrypt unchanged —
     * dispatch keys off the payload format, not off the write preference.
     */
    public function test_existing_sodium_payloads_still_decrypt_after_the_default_flipped(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_COMPAT_KEY', 'TEST_COMPAT_NONCE');
        $plaintext = 'sensitive-api-token-12345';

        $legacy = $this->invokeBackend($crypt, 'sodium_encrypt', $plaintext);

        $this->assertStringStartsNotWith(WP_Setting_Encryption::OPENSSL_PREFIX, $legacy);
        $this->assertSame($plaintext, $crypt->decrypt($legacy),
            'A sodium payload must keep decrypting now that openssl is the default writer');
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

        $this->assertStringStartsWith(WP_Setting_Encryption::OPENSSL_PREFIX_V2, $encrypted,
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

        $this->assertStringStartsWith(WP_Setting_Encryption::OPENSSL_PREFIX_V2, $encrypted,
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
        // A value as written by 3.0.x and earlier, before openssl became the default.
        $encrypted = $this->invokeBackend($crypt, 'sodium_encrypt', 'sensitive-api-token-12345');

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
        $marker  = substr($encrypted, 0, strrpos($encrypted, ':') + 1);
        $payload = base64_decode(substr($encrypted, strlen($marker)));
        // Flip a bit in the ciphertext body, past the IV and tag.
        $offset = WP_Setting_Encryption::OPENSSL_IV_LENGTH + WP_Setting_Encryption::DEFAULT_MAC_LENGTH;
        $payload[$offset] = chr(ord($payload[$offset]) ^ 0x01);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tampered');

        $crypt->decrypt($marker . base64_encode($payload));
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
     * try_decrypt() exists so a caller can tell a failed decrypt apart from a
     * successful one, which the swallowing wrapper makes impossible (#13).
     */
    public function test_try_decrypt_throws_runtime_exception_on_failure(): void
    {
        $this->setTextDomain('test-plugin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        \BGoewert\WP_Settings\WP_Setting::try_decrypt('not-actually-encrypted');
    }

    /**
     * The original failure has to survive the \RuntimeException normalization,
     * otherwise the caller trades a swallowed error for an unhelpful one.
     */
    public function test_try_decrypt_preserves_the_underlying_failure(): void
    {
        $this->setTextDomain('test-plugin');

        try {
            \BGoewert\WP_Settings\WP_Setting::try_decrypt('not-actually-encrypted');
            $this->fail('try_decrypt() should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious(),
                'the underlying failure should be kept as the previous exception');
        }
    }

    /**
     * An empty value is not a failure — nothing to decrypt, nothing to throw.
     */
    public function test_try_decrypt_returns_empty_value_untouched(): void
    {
        $this->setTextDomain('test-plugin');

        $this->assertSame('', \BGoewert\WP_Settings\WP_Setting::try_decrypt(''));
        $this->assertNull(\BGoewert\WP_Settings\WP_Setting::try_decrypt(null));
    }

    /**
     * try_encrypt()/try_decrypt() must round-trip through the same key/nonce
     * derivation as the swallowing wrappers, or values written one way would not
     * read back the other.
     */
    public function test_try_encrypt_round_trips_through_the_swallowing_wrapper(): void
    {
        if (!WP_Setting_Encryption::is_available()) {
            $this->markTestSkipped('Neither openssl nor sodium is loaded');
        }

        $this->setTextDomain('test-plugin');

        $encrypted = \BGoewert\WP_Settings\WP_Setting::try_encrypt('sensitive-api-token-12345');

        $this->assertNotSame('sensitive-api-token-12345', $encrypted, 'the value should be ciphered');
        $this->assertSame('sensitive-api-token-12345', \BGoewert\WP_Settings\WP_Setting::decrypt($encrypted));
        $this->assertSame('sensitive-api-token-12345', \BGoewert\WP_Settings\WP_Setting::try_decrypt($encrypted));
    }

    /**
     * A crypto failure still reaches the logger when the caller opts into the
     * throwing variant — the point of #13 was to keep that integration.
     */
    public function test_try_decrypt_still_logs_the_failure(): void
    {
        $this->setTextDomain('test-plugin');

        $logger = new class {
            public array $warnings = [];
            public function warning($message, $context = array()): void
            {
                $this->warnings[] = array($message, $context);
            }
        };

        \BGoewert\WP_Settings\WP_Setting::set_logger($logger);

        try {
            \BGoewert\WP_Settings\WP_Setting::try_decrypt('not-actually-encrypted');
            $this->fail('try_decrypt() should have thrown');
        } catch (\RuntimeException $e) {
            // Expected.
        } finally {
            \BGoewert\WP_Settings\WP_Setting::set_logger(null);
        }

        $this->assertSame(
            array(array('Decryption failed', array('operation' => 'decrypt'))),
            $logger->warnings
        );
    }

    /**
     * Point WP_Setting at a text domain, which is what the key/nonce constant
     * names are derived from.
     */
    private function setTextDomain(string $domain): void
    {
        $text_domain = (new ReflectionClass(\BGoewert\WP_Settings\WP_Setting::class))->getProperty('text_domain');
        $text_domain->setAccessible(true);
        $text_domain->setValue(null, $domain);
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
     * Regression test for the strlen(null) deprecation (2.28.2 regression).
     *
     * A key/nonce constant defined as `null` (e.g. `define('X_KEY', getenv('X_KEY') ?: null);`,
     * a common pattern for "not configured yet") used to flow unmodified through
     * safe_base64_decode() into check_key_len()/check_nonce_len(), reaching strlen() as
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
     * Regression test for the PHP 8.1+ strlen(null) / base64_decode(null) deprecation
     * emitted downstream by v2.29.0 (GitHub issue #4).
     *
     * Rather than proving every caller resolves to a string, the fix guards the leaf
     * helpers themselves so a null can never reach strlen()/base64_decode(). This
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

    /**
     * The fingerprint names the key, so it must be identical for two instances
     * resolving the same material and different for two that do not.
     */
    public function test_the_fingerprint_follows_the_key(): void
    {
        $same = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('a', 32), str_repeat('n', 24));
        $also = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('a', 32), str_repeat('n', 24));
        $other = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('b', 32), str_repeat('n', 24));

        $this->assertSame($same->key_fingerprint(), $also->key_fingerprint());
        $this->assertNotSame($same->key_fingerprint(), $other->key_fingerprint());
        $this->assertSame(WP_Setting_Encryption::FINGERPRINT_LENGTH, strlen($same->key_fingerprint()));
    }

    /**
     * A value written under a rotated key has to be distinguishable from one
     * that is merely corrupt, without decrypting it.
     */
    public function test_a_payload_reports_which_key_wrote_it(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $original = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('a', 32), str_repeat('n', 24));
        $rotated = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('b', 32), str_repeat('n', 24));

        $encrypted = $original->encrypt('sensitive-api-token-12345');

        $this->assertSame(WP_Setting_Encryption::KEY_CURRENT, $original->key_state($encrypted));
        $this->assertSame(WP_Setting_Encryption::KEY_DIFFERENT, $rotated->key_state($encrypted));
    }

    /**
     * Values written before fingerprinting say nothing about their key, and must
     * not be mistaken for values written under a different one.
     */
    public function test_a_payload_without_a_fingerprint_is_unknown(): void
    {
        $crypt = new WP_Setting_Encryption('TEST_STATE_KEY', 'TEST_STATE_NONCE');

        $this->assertSame(WP_Setting_Encryption::KEY_UNKNOWN, $crypt->key_state(WP_Setting_Encryption::OPENSSL_PREFIX . base64_encode('anything')));
        $this->assertSame(WP_Setting_Encryption::KEY_UNKNOWN, $crypt->key_state(base64_encode('a sodium-era payload')));
        $this->assertSame(WP_Setting_Encryption::KEY_UNKNOWN, $crypt->key_state(''));
    }

    /**
     * A v1 payload was written by 3.1.x and must still read back.
     */
    public function test_v1_payloads_still_decrypt(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $crypt = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('a', 32), str_repeat('n', 24));
        $plaintext = 'sensitive-api-token-12345';

        // Rebuild a v1 payload from a v2 one: same key, same body, older marker.
        $encrypted = $crypt->encrypt($plaintext);
        $v1 = WP_Setting_Encryption::OPENSSL_PREFIX . substr($encrypted, strrpos($encrypted, ':') + 1);

        $this->assertSame($plaintext, $crypt->decrypt($v1));
    }

    /**
     * Point WP_Setting at a text domain, and hand back a handle on the key it
     * would resolve for that domain.
     */
    private function settingsUnderDomain(string $domain): WP_Setting_Encryption
    {
        $text_domain = (new ReflectionClass(\BGoewert\WP_Settings\WP_Setting::class))->getProperty('text_domain');
        $text_domain->setAccessible(true);
        $text_domain->setValue(null, $domain);

        $encryption = (new ReflectionClass(\BGoewert\WP_Settings\WP_Setting::class))->getMethod('encryption');
        $encryption->setAccessible(true);

        return $encryption->invoke(null);
    }

    /**
     * The migration a plugin runs from its upgrade hook when it sunsets its own
     * key constant: values written under the old key become readable again.
     */
    public function test_rewrap_moves_values_onto_the_current_key(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $current = $this->settingsUnderDomain('rewrap-plugin');
        $legacy_key = str_repeat('L', 32);
        $legacy = new WP_Setting_Encryption(null, null, null, null, null, $legacy_key, str_repeat('N', 24));

        \BGoewert\WP_Settings\WP_Setting::set('api_token', $legacy->encrypt('sensitive-api-token-12345'));

        $results = \BGoewert\WP_Settings\WP_Setting::rewrap_encrypted(['api_token'], $legacy_key, str_repeat('N', 24));

        $this->assertSame(['api_token' => 'rewrapped'], $results);
        $this->assertSame(WP_Setting_Encryption::KEY_CURRENT, $current->key_state(\BGoewert\WP_Settings\WP_Setting::get('api_token')));
        $this->assertSame('sensitive-api-token-12345', \BGoewert\WP_Settings\WP_Setting::get('api_token', false, true));
    }

    /**
     * An upgrade hook can fire more than once, and a value must never be
     * encrypted twice.
     */
    public function test_rewrap_is_idempotent(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $this->settingsUnderDomain('rewrap-twice');
        $legacy_key = str_repeat('L', 32);
        $legacy = new WP_Setting_Encryption(null, null, null, null, null, $legacy_key, str_repeat('N', 24));

        \BGoewert\WP_Settings\WP_Setting::set('api_token', $legacy->encrypt('sensitive-api-token-12345'));
        \BGoewert\WP_Settings\WP_Setting::rewrap_encrypted(['api_token'], $legacy_key, str_repeat('N', 24));
        $after_first = \BGoewert\WP_Settings\WP_Setting::get('api_token');

        $results = \BGoewert\WP_Settings\WP_Setting::rewrap_encrypted(['api_token'], $legacy_key, str_repeat('N', 24));

        $this->assertSame(['api_token' => 'current'], $results);
        $this->assertSame($after_first, \BGoewert\WP_Settings\WP_Setting::get('api_token'));
        $this->assertSame('sensitive-api-token-12345', \BGoewert\WP_Settings\WP_Setting::get('api_token', false, true));
    }

    /**
     * One unreadable value must not cost the rest of the pass, and must not be
     * overwritten — the stored copy is the only copy.
     */
    public function test_rewrap_leaves_an_unreadable_value_alone_and_continues(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $this->settingsUnderDomain('rewrap-partial');
        $legacy_key = str_repeat('L', 32);
        $legacy = new WP_Setting_Encryption(null, null, null, null, null, $legacy_key, str_repeat('N', 24));

        \BGoewert\WP_Settings\WP_Setting::set('good_token', $legacy->encrypt('readable'));
        \BGoewert\WP_Settings\WP_Setting::set('bad_token', 'not-actually-encrypted');
        \BGoewert\WP_Settings\WP_Setting::set('blank_token', '');

        $results = \BGoewert\WP_Settings\WP_Setting::rewrap_encrypted(
            ['good_token', 'bad_token', 'blank_token'],
            $legacy_key,
            str_repeat('N', 24)
        );

        $this->assertSame(
            ['good_token' => 'rewrapped', 'bad_token' => 'failed', 'blank_token' => 'empty'],
            $results
        );
        $this->assertSame('not-actually-encrypted', \BGoewert\WP_Settings\WP_Setting::get('bad_token'));
        $this->assertSame('readable', \BGoewert\WP_Settings\WP_Setting::get('good_token', false, true));
    }

    /**
     * A rotated salt is unrecoverable, so the admin has to be told to re-enter
     * the value rather than sent to chase a rejected credential.
     */
    public function test_a_changed_key_is_reported_as_a_changed_key(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('openssl extension not loaded');
        }

        $this->settingsUnderDomain('rotated-plugin');
        $other = new WP_Setting_Encryption(null, null, null, null, null, str_repeat('R', 32), str_repeat('N', 24));
        $stored = $other->encrypt('sensitive-api-token-12345');

        try {
            \BGoewert\WP_Settings\WP_Setting::try_decrypt($stored);
            $this->fail('A value written under another key must not decrypt');
        } catch (\RuntimeException $e) {
            $this->assertSame(\BGoewert\WP_Settings\WP_Setting::CRYPT_KEY_CHANGED, $e->getCode());
        }

        $this->assertStringContainsString('encryption key changed', \BGoewert\WP_Settings\WP_Setting::decrypt_failure_message($stored));
        $this->assertStringContainsString('could not be decrypted', \BGoewert\WP_Settings\WP_Setting::decrypt_failure_message('not-actually-encrypted'));
    }
}
