## 1. Migration

- [x] 1.1 `WP_Settings_Key_Migration`: notice, `admin-post` handler, staging, `define()` removal with manual fallback
- [x] 1.2 Swap staged values in on the first request without the constants, verified under that request's key
- [x] 1.3 Re-stage a field saved while the migration waits
- [x] 1.4 Construct it from `WP_Settings`; swap in from `WP_Setting::get()` when decrypting
- [x] 1.5 Refuse on multisite

## 2. Tests and documentation

- [x] 2.1 Unit tests: stage, remove, manual fallback, unrecognized define, undecryptable field, swap, re-stage, notice, multisite
- [x] 2.2 README: the migration notice and what rotating the salts does afterward
- [x] 2.3 Update the `WP_Setting_Encryption` docblock that says nothing writes `wp-config.php`
