## 1. Encryption null-safety (mb_strlen bug)

- [x] 1.1 Read `encrypt()`/`decrypt()` to confirm how an empty-string key/nonce behaves, to settle
      the design's open question before choosing the coalesce value.
- [x] 1.2 Fix `get_default_key()` and `get_default_nonce()` in `WP_Setting_Encryption.php` so they
      never return `null` (coalesce to `''` or generate a value, per 1.1's finding).
- [x] 1.3 Add a regression test: instantiate `WP_Setting_Encryption` with no key/nonce constant
      defined and assert no deprecation notice is raised and a string is returned.

## 2. Settings section `name` key (undefined array key bug)

- [x] 2.1 Fix `WP_Settings::init()` to use a safe fallback for `$section["name"]`, matching the
      `$slug = $section["slug"] ?? $key;` pattern immediately above it.
- [x] 2.2 Add a regression test: register a section without a `name` key and assert
      `add_settings_section()` still runs without a PHP warning.

## 3. WP_Setting str_replace regression coverage

- [x] 3.1 Add a regression test exercising `WP_Setting::set()` and `WP_Setting::delete()` with a
      hyphenated text domain (the path that triggers `str_replace()` normalization), confirming no
      deprecation notice — locking in the fix already present on `main`.

## 4. Release

- [x] 4.1 Run the full test suite; confirm all three regression tests pass and no existing test
      regresses.
- [x] 4.2 Update `CHANGELOG.md` describing all three fixes together.
- [ ] 4.3 Cut a new release/tag.
- [x] 4.4 Note in the change (or a follow-up) that `wp-seiler-locations`, `wp-seiler-forms`, and
      `wp-mu-basic-auth` each need a dependency bump to this new version — tracked in those repos.
      (Already documented in `proposal.md`'s Impact section.)
