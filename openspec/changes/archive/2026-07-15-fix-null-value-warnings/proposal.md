## Why

Three consuming plugins (`wp-seiler-locations`, `wp-seiler-forms`, `wp-mu-basic-auth`) are
throwing PHP deprecation notices and warnings in production/staging/local logs, sourced from
`bgoewert/wp-settings`. These were surfaced while verifying a WordPress site's local DDEV
environment: `str_replace()`/`mb_strlen()` receiving `null` where a `string` is expected (PHP 8.1+
deprecation), and an undefined array key read. Consumers can't get a clean log by upgrading alone —
version 2.28.2 (currently vendored by `wp-seiler-forms` and `wp-mu-basic-auth`) fixed the older
`str_replace()` issue but introduced two new ones. Fixing all three in one pass, and cutting a
release, lets every downstream plugin bump to a single genuinely clean version.

## What Changes

- Fix `WP_Setting_Encryption::get_default_key()` / `get_default_nonce()` (or their callers
  `check_key_len()` / `check_nonce_len()`) so a missing/unset key or nonce constant no longer
  reaches `mb_strlen()` as `null` — coalesce to an empty string (or handle the "not configured yet"
  case explicitly) before the length check.
- Fix `WP_Settings::init()` — `$section["name"]` is read without the same `?? $key` /
  null-coalescing fallback used one line above it for `$section["slug"]`, causing an undefined
  array key warning when a registered section omits `name`. Add a safe default.
- Audit `WP_Setting.php`'s `str_replace()` call sites for the same class of bug: the two in
  `set()`/`delete()` already cast `$setting` to `(string)` before use and appear safe on current
  `main`, but the fix should be verified with a regression test and explicitly confirmed as no
  longer reachable with `null`, since this is the exact bug already reported against the CHANGELOG's
  fixed" v2.28.2 in one plugin while still open at v2.27.3 in another — the point of this change is
  a release that is unambiguously clean on all three.
- Add regression tests (or extend existing ones) covering: encryption instantiation without the
  key/nonce constants defined, and a settings section registered without a `name` key.
- Cut a new release/tag once fixed, so `wp-seiler-locations`, `wp-seiler-forms`, and
  `wp-mu-basic-auth` can all bump to the same clean version.

## Capabilities

### New Capabilities

- `settings-null-safety`: a testable guarantee that the library never passes `null` to a
  string-typed core function or reads an optional array key without a fallback, for the
  encryption key/nonce defaults and settings-section registration paths specifically.

### Modified Capabilities

None — no existing documented capability's requirements change; this establishes a new,
previously-undocumented guarantee rather than altering one already specified.

## Impact

- `src/WP_Setting_Encryption.php` — `get_default_key()`, `get_default_nonce()`,
  `check_key_len()`, `check_nonce_len()`.
- `src/WP_Settings.php` — `init()`, section registration (`add_settings_section` call site).
- `src/WP_Setting.php` — no code change expected, but call sites reviewed and covered by a
  regression test.
- Test suite: new/updated PHPUnit coverage for both fixed paths.
- Downstream: `wp-seiler-locations` (currently pinned to a version with the original
  `str_replace()` bug), `wp-seiler-forms` and `wp-mu-basic-auth` (currently on the version with the
  `mb_strlen()`/array-key bugs) each need a follow-up dependency bump once this ships — tracked in
  those repos, not here.
