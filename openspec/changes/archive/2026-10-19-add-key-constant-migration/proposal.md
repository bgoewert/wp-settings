## Why

Versions before 4.0.0 generated an encryption key and nonce on first use and wrote them into `wp-config.php` as `{TEXT_DOMAIN}_KEY` and `{TEXT_DOMAIN}_NONCE`. Current versions fall back to the WordPress salts, but a defined constant still wins, so every site that ran an old version keeps using the constants. On neigps.com the host's incident report listed seiler-forms' pair among the secrets exposed in a `wp-config.php` excerpt (SeilerInstrument/wp-seiler-forms#9). Getting off them today means deleting both lines and re-entering every encrypted setting, and nothing tells the admin the constants are there.

`rewrap_encrypted()` does not cover this: it re-encrypts under the key currently resolved, which is the constant until the lines are gone, and a value rewrapped after they are gone can no longer be read with them. Every consumer with an encrypted field (seiler-forms, seiler-products, seiler-support-portal, and others) has the same pair to retire, so the migration belongs here rather than in each plugin.

## What Changes

- A settings page whose key or nonce constant is defined shows administrators a notice with a **Migrate to WordPress salts** button.
- The button decrypts every encrypted field with the constants, stages the value re-encrypted under the salts, and deletes the two `define()` lines when `wp-config.php` is writable and file modifications are allowed. Otherwise the notice shows the lines to delete, values elided.
- The first request that loads without the constants swaps the staged values in, after checking each one decrypts under the key that request resolves. A value saved while the migration waits is re-staged.
- The library may delete, on that explicit admin action, only the `define()` lines its own pre-4.0 versions wrote. It still never reads key material from a configuration file.
- Not offered on multisite: `wp-config.php` is network-wide, and only the current site's values would move.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities

- `credential-encryption`: an admin can retire the legacy key constants from a notice.

## Impact

- New `src/WP_Settings_Key_Migration.php`, constructed by `WP_Settings`.
- `src/WP_Setting.php` — `get()` with `$decrypt` swaps staged values in first, so a front-end read after the lines are removed does not wait for an admin page load.
