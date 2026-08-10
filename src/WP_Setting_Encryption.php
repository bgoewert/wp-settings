<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

if (class_exists('BGoewert\\WP_Settings\\WP_Setting_Encryption')) {
    return;
}

/**
 * Handle encryption and decryption of data in WordPress using `libsodium`,
 * falling back to `openssl` (AES-256-GCM) where sodium is unavailable.
 * Expects keys to be stored in wp-config.
 * Note that this is not the safest but is the most reasonable method to support most installations of WordPress as far as I can tell.
 * @link https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/
 */
class WP_Setting_Encryption
{
    /** Key size in bytes. Matches SODIUM_CRYPTO_SECRETBOX_KEYBYTES and AES-256. */
    public const DEFAULT_KEY_LENGTH = 32;

    /** Nonce size in bytes. Matches SODIUM_CRYPTO_SECRETBOX_NONCEBYTES. */
    public const DEFAULT_NONCE_LENGTH = 24;

    /** Authentication tag size in bytes. Matches SODIUM_CRYPTO_SECRETBOX_MACBYTES and the GCM tag. */
    public const DEFAULT_MAC_LENGTH = 16;

    /** Cipher used by the openssl fallback. AEAD, like secretbox. */
    public const OPENSSL_CIPHER = 'aes-256-gcm';

    /** AES-GCM IV size in bytes. */
    public const OPENSSL_IV_LENGTH = 12;

    /**
     * Marker prefixed to openssl payloads so the two ciphertext formats can be
     * told apart on read. ':' and '.' are outside the base64 alphabet, so a
     * sodium payload can never be mistaken for an openssl one.
     */
    public const OPENSSL_PREFIX = 'wps.aesgcm.v1:';

    private $key;
    private $nonce;
    private static $instance;

    private $key_constant;
    private $nonce_constant;
    // Resolved in the constructor, not here: property initialisers are evaluated
    // at instantiation, so referencing SODIUM_* constants at this point makes the
    // class unconstructable — and the extension_loaded() guards unreachable — on
    // PHP builds without sodium.
    private $key_length;
    private $nonce_length;
    private $mac_length;

    public function __construct($key_constant = \null, $nonce_constant = \null, $key_length = \null, $nonce_length = \null, $mac_length = \null)
    {
        $this->key_length = \defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES') ? \SODIUM_CRYPTO_SECRETBOX_KEYBYTES : self::DEFAULT_KEY_LENGTH;
        $this->nonce_length = \defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES') ? \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : self::DEFAULT_NONCE_LENGTH;
        $this->mac_length = \defined('SODIUM_CRYPTO_SECRETBOX_MACBYTES') ? \SODIUM_CRYPTO_SECRETBOX_MACBYTES : self::DEFAULT_MAC_LENGTH;

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
        // Guard null: PHP 8.1+ deprecates passing null to base64_decode()/strlen().
        // An unset/empty encrypted option arrives here as null; coerce to '' so it
        // round-trips cleanly instead of propagating null into decrypt().
        $value = (string) $value;

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
        // Guard null: PHP 8.1+ deprecates passing null to strlen(). A key/salt
        // that hasn't been resolved yet can arrive here as null.
        $key = (string) $key;
        if (strlen($key) > $this->key_length) {
            $key = substr($key, 0, $this->key_length);
        }
        return $key;
    }

    private function check_nonce_len($nonce)
    {
        // Guard null: PHP 8.1+ deprecates passing null to strlen().
        $nonce = (string) $nonce;
        if (strlen($nonce) > $this->nonce_length) {
            $nonce = substr($nonce, 0, $this->nonce_length);
        }
        return $nonce;
    }

    /**
     * Write a define() constant to wp-config.php before the require_once wp-settings.php line.
     * Falls back to FILE_APPEND if the require_once line is not found (non-standard installations).
     *
     * @param string $config_file   Absolute path to wp-config.php.
     * @param string $constant_line The full define() line to insert (e.g. "define('KEY', 'value');\n").
     * @return void
     */
    private function write_config_constant(string $config_file, string $constant_line): void
    {
        $content = file_get_contents($config_file);
        // Match require_once wp-settings.php in various formats (tabs, spaces, parentheses)
        $pattern = '/^(\s*require_once\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"]\s*\)?\s*;)/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $constant_line . '$1', $content, 1);
            file_put_contents($config_file, $content);
        } else {
            // Fallback: append to end of file
            file_put_contents($config_file, $constant_line, FILE_APPEND);
        }
    }

    private function get_default_key()
    {
        $config_file = ABSPATH . 'wp-config.php';

        if (
            file_exists($config_file)
            && (
                (defined($this->key_constant) && '' !== constant($this->key_constant))
                || preg_match("/define\('{$this->key_constant}',\s?'[^']+'\);/", file_get_contents($config_file))
            )
        ) {
            if (!defined($this->key_constant)) {
                return $this->check_key_len(self::safe_base64_decode(preg_match("/define\('{$this->key_constant}',\s?'([^']+)'\);/", file_get_contents($config_file), $matches) ? $matches[1] : ''));
            }
            return $this->check_key_len(self::safe_base64_decode((string) constant($this->key_constant)));
        } else if (is_writable($config_file)) {
            $key = self::random_bytes($this->key_length);
            $key_constant = "define('" . $this->key_constant . "', '" . base64_encode($key) . "');\n";
            $this->write_config_constant($config_file, $key_constant);
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
                || preg_match("/define\('{$this->nonce_constant}',\s?'[^']+'\);/", file_get_contents($config_file))
            )
        ) {
            if (!defined($this->nonce_constant)) {
                return $this->check_nonce_len(self::safe_base64_decode(preg_match("/define\('{$this->nonce_constant}',\s?'([^']+)'\);/", file_get_contents($config_file), $matches) ? $matches[1] : ''));
            }
            return $this->check_nonce_len(self::safe_base64_decode((string) constant($this->nonce_constant)));
        } else if (is_writable($config_file)) {
            $nonce = self::random_bytes($this->nonce_length);
            $nonce_constant = "define('" . $this->nonce_constant . "', '" . base64_encode($nonce) . "');\n";
            $this->write_config_constant($config_file, $nonce_constant);
            return $nonce;
        }

        if (defined('NONCE_KEY') && '' !== \NONCE_KEY) {
            return $this->check_nonce_len(\NONCE_KEY);
        }

        // you've gone to far
        return 'ta-n-uimhir-shoh-soilshaghey-ny-mooar-ny-un-uair';
    }

    /**
     * Whether this installation can encrypt at all.
     *
     * @return bool True when either sodium or openssl is available.
     */
    public static function is_available(): bool
    {
        return extension_loaded('openssl') || extension_loaded('sodium');
    }

    /**
     * Derive the AES-256-GCM key.
     *
     * AES-256 needs exactly 32 bytes, but check_key_len() only truncates — a
     * short key (LOGGED_IN_KEY, or the last-resort literal) would otherwise rely
     * on openssl silently NUL-padding it. Hashing normalises the length
     * deterministically.
     *
     * @return string 32 raw bytes.
     */
    private function openssl_key(): string
    {
        return hash('sha256', (string) $this->key, true);
    }

    /**
     * Encrypt with AES-256-GCM.
     *
     * Unlike the sodium path this generates a fresh IV per call rather than
     * reusing the configured nonce — IV reuse under GCM is catastrophic.
     *
     * @param string $string Plaintext.
     * @return string Prefixed, base64-encoded `IV . tag . ciphertext`.
     * @throws \RuntimeException When openssl_encrypt() fails.
     */
    private function openssl_encrypt(string $string): string
    {
        $iv  = self::random_bytes(self::OPENSSL_IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt($string, self::OPENSSL_CIPHER, $this->openssl_key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::DEFAULT_MAC_LENGTH);

        if (false === $cipher) {
            throw new \RuntimeException('Error encrypting. openssl_encrypt() failed.');
        }

        return self::OPENSSL_PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt an AES-256-GCM payload written by openssl_encrypt().
     *
     * @param string $encrypted_string Prefixed, base64-encoded payload.
     * @return string The plaintext.
     * @throws \RuntimeException When the payload is truncated or fails authentication.
     */
    private function openssl_decrypt(string $encrypted_string): string
    {
        $decoded = base64_decode(substr($encrypted_string, strlen(self::OPENSSL_PREFIX)), true);

        if (false === $decoded || strlen($decoded) < self::OPENSSL_IV_LENGTH + self::DEFAULT_MAC_LENGTH) {
            throw new \RuntimeException('Error decrypting. The given string was truncated.');
        }

        $iv        = substr($decoded, 0, self::OPENSSL_IV_LENGTH);
        $tag       = substr($decoded, self::OPENSSL_IV_LENGTH, self::DEFAULT_MAC_LENGTH);
        $cipher    = substr($decoded, self::OPENSSL_IV_LENGTH + self::DEFAULT_MAC_LENGTH);
        $decrypted = openssl_decrypt($cipher, self::OPENSSL_CIPHER, $this->openssl_key(), OPENSSL_RAW_DATA, $iv, $tag);

        if (false === $decrypted) {
            throw new \RuntimeException('Error decrypting. The string was tampered with in transit.');
        }

        return $decrypted;
    }

    /**
     * Encrypt with XSalsa20-Poly1305.
     *
     * Retained for hosts without openssl, and as the format every value written
     * before 3.1.0 is in. Note this reuses the configured nonce for every value.
     *
     * @param string $string Plaintext.
     * @return string Base64-encoded `nonce . ciphertext`, unprefixed.
     */
    private function sodium_encrypt(string $string): string
    {
        $cipher    = sodium_crypto_secretbox($string, $this->nonce, $this->key);
        $encrypted = base64_encode($this->nonce . $cipher);

        sodium_memzero($string);

        return $encrypted;
    }

    /**
     * Decrypt an XSalsa20-Poly1305 payload written by sodium_encrypt().
     *
     * @param string $encrypted_string Base64-encoded `nonce . ciphertext`.
     * @return string The plaintext.
     * @throws \RuntimeException When the payload is truncated or fails authentication.
     */
    private function sodium_decrypt(string $encrypted_string): string
    {
        $decoded = base64_decode($encrypted_string);

        if (strlen($decoded) < $this->nonce_length + $this->mac_length) {
            throw new \RuntimeException('Error decrypting. The given string was truncated.');
        }

        $nonce     = substr($decoded, 0, $this->nonce_length);
        $cipher    = substr($decoded, $this->nonce_length);
        $decrypted = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        if (false === $decrypted) {
            throw new \RuntimeException('Error decrypting. The string was tampered with in transit.');
        }

        return $decrypted;
    }

    /**
     * Encrypt a value, preferring openssl and falling back to sodium.
     *
     * openssl is the wider bet: WordPress leans on it for HTTPS, whereas sodium
     * is only bundled with PHP, not guaranteed to be built (`--with-sodium`), and
     * is routinely absent from minimal and cross-compiled builds. Writing openssl
     * by default keeps a value readable if the site later moves to such a host.
     * The openssl path is also the stronger of the two here: it derives a fresh
     * IV per value rather than reusing the configured nonce.
     *
     * Existing sodium ciphertexts are unaffected — decrypt() dispatches on the
     * payload format, not on this preference.
     *
     * @param string $string The plaintext to encrypt.
     * @return string The encrypted value.
     * @throws \RuntimeException When no supported extension is loaded, or encryption fails.
     */
    public function encrypt($string)
    {
        $string = (string) $string;

        if (extension_loaded('openssl')) {
            return $this->openssl_encrypt($string);
        }

        if (extension_loaded('sodium')) {
            return $this->sodium_encrypt($string);
        }

        throw new \RuntimeException('Neither the openssl nor the sodium extension is loaded. Encryption cannot be completed.');
    }

    /**
     * Decrypt a value, dispatching on the format the payload was written in.
     *
     * @param string $encrypted_string The value to decrypt.
     * @return string The decrypted value.
     * @throws \RuntimeException When the required extension is missing, or the payload is truncated or tampered with.
     */
    public function decrypt($encrypted_string)
    {
        $encrypted_string = (string) $encrypted_string;

        if ('' === $encrypted_string) {
            return '';
        }

        if (str_starts_with($encrypted_string, self::OPENSSL_PREFIX)) {
            if (!extension_loaded('openssl')) {
                throw new \RuntimeException('The openssl extension is not loaded. Decryption cannot be completed.');
            }

            return $this->openssl_decrypt($encrypted_string);
        }

        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('The sodium extension is not loaded. Decryption cannot be completed.');
        }

        return $this->sodium_decrypt($encrypted_string);
    }
}
