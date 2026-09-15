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
 * Key material comes from a constant, an environment variable of the same name,
 * or the WordPress salts — never from parsing or writing a config file.
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

    /**
     * Marker for openssl payloads that carry the fingerprint of the key that
     * wrote them, as `wps.aesgcm.v2:<fingerprint>:<base64>`. Without it a value
     * encrypted under a rotated key is indistinguishable from a corrupt one.
     */
    public const OPENSSL_PREFIX_V2 = 'wps.aesgcm.v2:';

    /** Fingerprint length in hex characters. Identifies a key; it does not verify one. */
    public const FINGERPRINT_LENGTH = 8;

    /** The payload was written under the key currently in use. */
    public const KEY_CURRENT = 'current';

    /** The payload names a key, and it is not the one in use. */
    public const KEY_DIFFERENT = 'different';

    /** The payload predates fingerprinting, so nothing can be said about its key. */
    public const KEY_UNKNOWN = 'unknown';

    private $key;
    private $nonce;
    private static $instance;

    private $key_constant;
    private $nonce_constant;

    /** Where the key and nonce were resolved from: 'constant', 'env', 'salt' or 'fallback'. */
    private $key_source;
    private $nonce_source;
    // Resolved in the constructor, not here: property initialisers are evaluated
    // at instantiation, so referencing SODIUM_* constants at this point makes the
    // class unconstructable — and the extension_loaded() guards unreachable — on
    // PHP builds without sodium.
    private $key_length;
    private $nonce_length;
    private $mac_length;

    /**
     * @param string|null $key_constant   Name of the constant/env var holding the key.
     * @param string|null $nonce_constant Name of the constant/env var holding the nonce.
     * @param int|null    $key_length     Key length in bytes.
     * @param int|null    $nonce_length   Nonce length in bytes.
     * @param int|null    $mac_length     Authentication tag length in bytes.
     * @param string|null $key            Raw key, bypassing resolution. For reading values
     *                                    written under a key the site no longer resolves to.
     * @param string|null $nonce          Raw nonce, bypassing resolution.
     */
    public function __construct($key_constant = \null, $nonce_constant = \null, $key_length = \null, $nonce_length = \null, $mac_length = \null, $key = \null, $nonce = \null)
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

        if (\null !== $key) {
            $this->key = $this->check_key_len((string) $key);
            $this->key_source = 'explicit';
        } else {
            $this->key = $this->get_default_key();
        }

        if (\null !== $nonce) {
            $this->nonce = $this->check_nonce_len((string) $nonce);
            $this->nonce_source = 'explicit';
        } else {
            $this->nonce = $this->get_default_nonce();
        }
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
     * Resolve secret material from the environment.
     *
     * A defined constant first, then an environment variable of the same name,
     * then the WordPress salt. The salt is the documented default because it
     * needs no provisioning; a dedicated constant is the opt-out for sites that
     * rotate salts. Nothing is read from, or written to, wp-config.php — the
     * file is unwritable on most hardened and containerized deploys, and a
     * plugin rewriting it by regex does not survive an atomic release.
     *
     * @param string|null $constant    Constant/env var name, or null when the caller named none.
     * @param string      $salt        WordPress salt constant to fall back to.
     * @param int         $length      Maximum length in bytes.
     * @param string      $last_resort Value used when every source is absent.
     * @return array{0:string,1:string} The material, and the source it came from.
     */
    private function resolve_secret($constant, string $salt, int $length, string $last_resort): array
    {
        if (\is_string($constant) && '' !== $constant) {
            if (defined($constant) && '' !== (string) constant($constant)) {
                return [$this->truncate(self::safe_base64_decode((string) constant($constant)), $length), 'constant'];
            }

            $from_env = getenv($constant);
            if (\is_string($from_env) && '' !== $from_env) {
                return [$this->truncate(self::safe_base64_decode($from_env), $length), 'env'];
            }
        }

        if (defined($salt) && '' !== (string) constant($salt)) {
            return [$this->truncate((string) constant($salt), $length), 'salt'];
        }

        return [$this->truncate($last_resort, $length), 'fallback'];
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length) : $value;
    }

    private function get_default_key()
    {
        // the last argument is where you've gone too far
        [$key, $this->key_source] = $this->resolve_secret($this->key_constant, 'LOGGED_IN_KEY', $this->key_length, 'cha nel-shoh-alkey-folliaght');

        return $key;
    }

    private function get_default_nonce()
    {
        [$nonce, $this->nonce_source] = $this->resolve_secret($this->nonce_constant, 'NONCE_KEY', $this->nonce_length, 'ta-n-uimhir-shoh-soilshaghey-ny-mooar-ny-un-uair');

        return $nonce;
    }

    /**
     * Where the key was resolved from: 'constant', 'env', 'salt', 'explicit' or 'fallback'.
     *
     * A "the key changed" message is only actionable if it can name the source.
     *
     * @return string
     */
    public function key_source(): string
    {
        return (string) $this->key_source;
    }

    /**
     * Identify the key in use, without revealing it.
     *
     * Truncated deliberately: this names a key so a payload can say which one
     * wrote it, and it sits in the datastore next to the ciphertext. A full
     * digest of secret material there would only help an offline attacker.
     *
     * @return string Hex, {@see self::FINGERPRINT_LENGTH} characters.
     */
    public function key_fingerprint(): string
    {
        return substr(hash('sha256', 'wps-key-fingerprint|' . (string) $this->key), 0, self::FINGERPRINT_LENGTH);
    }

    /**
     * Whether a stored payload was written under the key currently in use.
     *
     * Reads the fingerprint only — no decryption, so this is safe to call on a
     * value that cannot be read.
     *
     * @param string $encrypted_string The stored value.
     * @return string One of self::KEY_CURRENT, self::KEY_DIFFERENT, self::KEY_UNKNOWN.
     */
    public function key_state($encrypted_string): string
    {
        $fingerprint = self::payload_fingerprint((string) $encrypted_string);

        if (null === $fingerprint) {
            return self::KEY_UNKNOWN;
        }

        return hash_equals($this->key_fingerprint(), $fingerprint) ? self::KEY_CURRENT : self::KEY_DIFFERENT;
    }

    /**
     * Read the key fingerprint out of a payload.
     *
     * @param string $encrypted_string The stored value.
     * @return string|null The fingerprint, or null for a payload written before fingerprinting.
     */
    private static function payload_fingerprint(string $encrypted_string): ?string
    {
        if (!str_starts_with($encrypted_string, self::OPENSSL_PREFIX_V2)) {
            return null;
        }

        $fingerprint = substr($encrypted_string, strlen(self::OPENSSL_PREFIX_V2), self::FINGERPRINT_LENGTH);

        return 1 === preg_match('/^[0-9a-f]{' . self::FINGERPRINT_LENGTH . '}$/', $fingerprint) ? $fingerprint : null;
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
     * Derive the secretbox key.
     *
     * secretbox demands exactly 32 bytes and throws otherwise, so a salt or
     * fallback shorter than that used to make the sodium path unusable. A key
     * already the right length is passed through untouched — hashing it would
     * orphan every payload written before this.
     *
     * @return string 32 raw bytes.
     */
    private function sodium_key(): string
    {
        $key = (string) $this->key;

        return $this->key_length === strlen($key) ? $key : substr(hash('sha256', $key, true), 0, $this->key_length);
    }

    /**
     * Derive the secretbox nonce, on the same terms as {@see self::sodium_key()}.
     *
     * @return string Exactly nonce_length raw bytes.
     */
    private function sodium_nonce(): string
    {
        $nonce = (string) $this->nonce;

        return $this->nonce_length === strlen($nonce) ? $nonce : substr(hash('sha256', $nonce, true), 0, $this->nonce_length);
    }

    /**
     * Encrypt with AES-256-GCM.
     *
     * Unlike the sodium path this generates a fresh IV per call rather than
     * reusing the configured nonce — IV reuse under GCM is catastrophic.
     *
     * @param string $string Plaintext.
     * @return string `wps.aesgcm.v2:<fingerprint>:` followed by base64 `IV . tag . ciphertext`.
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

        return self::OPENSSL_PREFIX_V2 . $this->key_fingerprint() . ':' . base64_encode($iv . $tag . $cipher);
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
        $offset = str_starts_with($encrypted_string, self::OPENSSL_PREFIX_V2)
            ? strlen(self::OPENSSL_PREFIX_V2) + self::FINGERPRINT_LENGTH + 1
            : strlen(self::OPENSSL_PREFIX);

        $decoded = base64_decode(substr($encrypted_string, $offset), true);

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
        $cipher    = sodium_crypto_secretbox($string, $this->sodium_nonce(), $this->sodium_key());
        $encrypted = base64_encode($this->sodium_nonce() . $cipher);

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
        $decrypted = sodium_crypto_secretbox_open($cipher, $nonce, $this->sodium_key());

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

        if (str_starts_with($encrypted_string, self::OPENSSL_PREFIX_V2) || str_starts_with($encrypted_string, self::OPENSSL_PREFIX)) {
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
