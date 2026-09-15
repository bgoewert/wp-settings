## Why

`WP_Setting_Encryption` provisions its key and nonce by grepping `wp-config.php` for a `define()` and, failing that, writing one into the file. On Bedrock, Trellis, or any deploy that ships a read-only or minimal `wp-config.php`, the grep can never match and the write silently no-ops, so the class falls back to `LOGGED_IN_KEY`/`NONCE_KEY` without saying so. Rotating auth salts then makes every stored credential unreadable, and the plugin reports that as a rejected credential rather than a changed key.

## What Changes

- Resolve the key and nonce from `defined()`, then `getenv()`, then the WordPress salts. One path covers `.env`, Docker, and hosting panels; the salt fallback stays the documented default because it needs no provisioning.
- **BREAKING** Remove `write_config_constant()` and the `wp-config.php` grep. The library never writes a config file, and a constant that exists only as unexecuted text in `wp-config.php` is no longer read.
- Record a non-reversible fingerprint of the resolved key so a key change is detectable, and surface "the encryption key changed, re-enter this value" on the settings screen instead of letting a failed decrypt read as an authentication failure.
- Add a public one-shot rewrap routine a consuming plugin calls from its own upgrade hook. It decrypts every registered encrypted setting with an explicitly supplied legacy key source and re-encrypts under current resolution, so a site moving off a custom constant — or off the salts — migrates its stored credentials in one pass rather than losing them.
- Document the two constants, how to generate a value (`openssl rand -base64 32`), and when a rewrap is required.

## Capabilities

### New Capabilities
- `credential-encryption`: how the encryption key and nonce are resolved, how a key change is detected and reported, and how stored values are rewrapped from a legacy key to the current one.

### Modified Capabilities
<!-- None. `settings-null-safety` requires the key/nonce resolvers to return a string rather than
     null; that requirement is unchanged and continues to hold under the new resolution order. -->

## Impact

- `src/WP_Setting_Encryption.php` — resolution order, removal of the config-file read and write, key fingerprinting, and a constructor path that accepts an explicit key/nonce for the legacy side of a rewrap.
- `src/WP_Setting.php` — the public rewrap entry point and the decrypt-failure reporting that distinguishes a changed key from a bad value.
- Consuming plugins that relied on the library provisioning a constant for them must now supply one or accept the salt fallback; those already carrying a custom constant are unaffected, since a `define()` in an executed `wp-config.php` already satisfies `defined()`.
- README: the encryption section gains the constant names, generation command, and the upgrade-hook snippet.
