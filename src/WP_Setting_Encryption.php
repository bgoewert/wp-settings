<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Handle encryption and decryption of data in WordPress using `libsodium`.
 * Expects keys to be stored in wp-config.
 * Note that this is not the safest but is the most reasonable method to support most installations of WordPress as far as I can tell.
 * @link https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/
 * @todo: Add fallback for openssl (if it exists)
 */
class WP_Setting_Encryption
{
    private $key;
    private $nonce;
    private static $instance;

    private $key_constant;
    private $nonce_constant;
    private $key_length = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    private $nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
    private $mac_length = SODIUM_CRYPTO_SECRETBOX_MACBYTES;

    public function __construct($key_constant = \null, $nonce_constant = \null, $key_length = \null, $nonce_length = \null, $mac_length = \null)
    {
        if (\null !== $key_length) {
            $this->key_length = $key_length;
        }
        if (\null !== $nonce_length) {
            $this->nonce_length = $nonce_length;
        }
        if (\null !== $key_constant) {
            $this->key_constant = $key_constant;
        }
        if (\null !== $nonce_constant) {
            $this->nonce_constant = $nonce_constant;
        }
        if (\null !== $mac_length) {
            $this->mac_length = $mac_length;
        }

        $this->key = $this->get_default_key();
        $this->nonce = $this->get_default_nonce();
    }

    public static function get_instance()
    {
        // TODO: Add locking mechanism to prevent multiple instances?
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function random_bytes($length)
    {
        if (function_exists('random_bytes')) {
            /** @disregard p1010 Undefined function */
            return \random_bytes($length);
        }

        return openssl_random_pseudo_bytes($length);
    }

    /**
     * Safely decode a value that might be base64-encoded
     *
     * If the value is base64-encoded (like wp-settings generates), decode it.
     * If it's raw bytes or a non-base64 string, return as-is for backward compatibility.
     *
     * @param string $value The value to decode
     * @return string The decoded value or original if not base64
     */
    private static function safe_base64_decode($value)
    {
        // Try to decode
        $decoded = base64_decode($value, true);

        // If decode failed or the value isn't valid base64, return original
        if ($decoded === false) {
            return $value;
        }

        // Verify it's actually base64 by re-encoding and comparing
        // This prevents false positives from strings that happen to decode
        if (base64_encode($decoded) === $value) {
            return $decoded;
        }

        // Not valid base64, return original value
        return $value;
    }

    private function check_key_len($key)
    {
        if (mb_strlen($key, '8bit') > $this->key_length) {
            $key = mb_substr($key, 0, $this->key_length, '8bit');
        }
        return $key;
    }

    private function check_nonce_len($nonce)
    {
        if (mb_strlen($nonce, '8bit') > $this->nonce_length) {
            $nonce = mb_substr($nonce, 0, $this->nonce_length, '8bit');
        }
        return $nonce;
    }

    private function get_default_key()
    {
        $config_file = ABSPATH . 'wp-config.php';

        if (
            file_exists($config_file)
            && (
                (defined($this->key_constant) && '' !== constant($this->key_constant))
                || preg_match("/define\('{$this->key_constant}',\s?'[\w\W\d]{{$this->key_length},}'\);/", file_get_contents($config_file))
            )
        ) {
            if (!defined($this->key_constant)) {
                return $this->check_key_len(self::safe_base64_decode(preg_match("/define\('{$this->key_constant}',\s?'([\w\W\d]{{$this->key_length},})'\);/", file_get_contents($config_file), $matches) ? $matches[1] : ''));
            }
            return $this->check_key_len(self::safe_base64_decode(constant($this->key_constant)));
        } else if (is_writable($config_file)) {
            $key = self::random_bytes($this->key_length);
            $key_constant = "define('" . $this->key_constant . "', '" . base64_encode($key) . "');\n";
            file_put_contents($config_file, $key_constant, FILE_APPEND);
            return $key;
        }

        if (defined('LOGGED_IN_KEY') && '' !== LOGGED_IN_KEY) {
            return $this->check_key_len(LOGGED_IN_KEY);
        }

        // you've gone to far
        return 'cha nel-shoh-alkey-folliaght';
    }

    private function get_default_nonce()
    {
        $config_file = ABSPATH . 'wp-config.php';

        if (
            file_exists($config_file)
            && (
                (defined($this->nonce_constant) && '' !== constant($this->nonce_constant))
                || preg_match("/define\('{$this->nonce_constant}',\s?'[\w\W\d]{{$this->nonce_length},}'\);/", file_get_contents($config_file))
            )
        ) {
            if (!defined($this->nonce_constant)) {
                return $this->check_nonce_len(self::safe_base64_decode(preg_match("/define\('{$this->nonce_constant}',\s?'([\w\W\d]{{$this->nonce_length},})'\);/", file_get_contents($config_file), $matches) ? $matches[1] : ''));
            }
            return $this->check_nonce_len(self::safe_base64_decode(constant($this->nonce_constant)));
        } else if (is_writable($config_file)) {
            $nonce = self::random_bytes($this->nonce_length);
            $config_file = $config_file;
            $nonce_constant = "define('" . $this->nonce_constant . "', '" . base64_encode($nonce) . "');\n";
            file_put_contents($config_file, $nonce_constant, FILE_APPEND);
            return $nonce;
        }

        if (defined('NONCE_KEY') && '' !== \NONCE_KEY) {
            return $this->check_nonce_len(\NONCE_KEY);
        }

        // you've gone to far
        return 'ta-n-uimhir-shoh-soilshaghey-ny-mooar-ny-un-uair';
    }

    public function encrypt($string)
    {
        if (!extension_loaded('sodium')) {
            // trigger_error('The Sodium extension is not loaded. Encryption cannot be completed. Returned initial value.', E_USER_ERROR);
            return new \Error('The Sodium extension is not loaded. Encryption cannot be completed. Returned initial value.');
        }

        $cipher    = sodium_crypto_secretbox($string, $this->nonce, $this->key);
        $encrypted = base64_encode($this->nonce . $cipher);

        sodium_memzero($string);
        sodium_memzero($this->key);

        return $encrypted;
    }

    public function decrypt($encrypted_string)
    {
        if (!extension_loaded('sodium')) {
            // trigger_error('The Sodium extension is not loaded. Decryption cannot be completed. Returned initial string.', E_USER_ERROR);
            return new \Error('The Sodium extension is not loaded. Decryption cannot be completed. Returned initial string.');
        }

        if (empty($encrypted_string)) {
            return '';
        }

        $decoded = base64_decode($encrypted_string);

        if (mb_strlen($decoded, '8bit') < $this->nonce_length + $this->mac_length) {
            // trigger_error('Error decrypting. The given string was truncated.', E_USER_WARNING);
            return new \Error('Error decrypting. The given string was truncated.');
        }

        $nonce     = mb_substr($decoded, 0, $this->nonce_length, '8bit');
        $cipher    = mb_substr($decoded, $this->nonce_length, \null, '8bit');
        $decrypted = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        if (false === $decrypted) {
            // trigger_error('Error decrypting. The string was tampered with in transit.', E_USER_WARNING);
            return new \Error('Error decrypting. The string was tampered with in transit.');
        }

        return $decrypted;
    }
}
