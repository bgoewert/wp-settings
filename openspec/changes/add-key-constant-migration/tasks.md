## 1. Migration

- [ ] 1.1 `WP_Settings_Key_Migration`: notice, `admin-post` handler, staging, `define()` removal with manual fallback
- [ ] 1.2 Swap staged values in on the first request without the constants, verified under that request's key
- [ ] 1.3 Re-stage a field saved while the migration waits
- [ ] 1.4 Construct it from `WP_Settings`; swap in from `WP_Setting::get()` when decrypting
- [ ] 1.5 Refuse on multisite

## 2. Tests and documentation

- [ ] 2.1 Unit tests: stage, remove, manual fallback, unrecognized define, undecryptable field, swap, re-stage, notice, multisite
- [ ] 2.2 README: the migration notice and what rotating the salts does afterward
- [ ] 2.3 Update the `WP_Setting_Encryption` docblock that says nothing writes `wp-config.php`
