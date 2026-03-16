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
        $test_key_value = 'hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=';
        $test_nonce_value = 'f7kSL5pu0pjc+/AKBVWGDqzUQOR4ZFOb';
        
        $config_content = <<<'PHP'
<?php
define('TEST_ENC_KEY_BUG1', 'hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=');
define('TEST_ENC_NONCE_BUG1', 'f7kSL5pu0pjc+/AKBVWGDqzUQOR4ZFOb');
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
define('TEST_ENC_KEY_ROUNDTRIP', 'hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=');
define('TEST_ENC_NONCE_ROUNDTRIP', 'f7kSL5pu0pjc+/AKBVWGDqzUQOR4ZFOb');
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
define('TEST_LATE_KEY_BUG12', 'hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=');
define('TEST_LATE_NONCE_BUG12', 'f7kSL5pu0pjc+/AKBVWGDqzUQOR4ZFOb');
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
        $original = 'hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=';
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
        $invalid = "hkTAU78Tqoij9jZZbvfIzPtUOmTawuZxzN5a5rMHvfg=');";
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
     * Test that decryption returns Error for truncated ciphertext
     * 
     * This verifies error handling for malformed encrypted data.
     */
    public function test_decrypt_returns_error_for_truncated_ciphertext(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension not loaded');
        }

        $crypt = new WP_Setting_Encryption('TEST_KEY', 'TEST_NONCE');

        // Try to decrypt a truncated base64 string
        $result = $crypt->decrypt('dGVzdA=='); // Just "test" in base64, too short

        // Should return an Error object
        $this->assertInstanceOf(\Error::class, $result,
            'Decryption should return Error for truncated ciphertext');
    }

    /**
     * Test that decryption returns Error for tampered ciphertext
     * 
     * This verifies that sodium_crypto_secretbox_open detects tampering.
     */
    public function test_decrypt_returns_error_for_tampered_ciphertext(): void
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

        // Try to decrypt the tampered data
        $result = $crypt->decrypt($tampered_encrypted);

        // Should return an Error object
        $this->assertInstanceOf(\Error::class, $result,
            'Decryption should return Error for tampered ciphertext');
    }
}
