# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on [Common Changelog](https://common-changelog.org/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [3.1.0] - 2026-08-10

### Changed

- Encryption now writes with openssl (AES-256-GCM) by default, falling back to sodium where openssl is unavailable — the reverse of 3.0.0. openssl is the wider bet: WordPress leans on it for HTTPS, whereas sodium is only *bundled* with PHP and still has to be enabled at build time (`--with-sodium`), so it is routinely absent from minimal and cross-compiled builds. Writing openssl by default keeps a value readable if the site later moves to such a host, and the openssl path is the stronger of the two here because it derives a fresh IV per value instead of reusing the configured nonce.
- Reading is unaffected and needs no migration: `decrypt()` dispatches on the payload's own format, so values written by 3.0.x and earlier keep decrypting as long as sodium is present. Values written from now on carry the `wps.aesgcm.v1:` marker.

## [3.0.1] - 2026-08-10

### Changed

- `WP_Setting_Encryption` no longer needs `ext-mbstring`. Every `mb_strlen()`/`mb_substr()` call in the class already passed the `'8bit'` encoding, which is byte-for-byte what `strlen()`/`substr()` do, so the extension was a silent hard requirement of the encryption path buying nothing. It was undeclared, and a build without it would have failed the same way the missing sodium constants did in [#12](https://github.com/bgoewert/wp-settings/issues/12). Behaviour is unchanged.

## [3.0.0] - 2026-08-10

### Changed

- **Breaking:** `WP_Setting_Encryption::encrypt()` and `decrypt()` now throw `\RuntimeException` on failure instead of returning an `\Error` object. The old behaviour handed back a value the caller could not use as a string, and contradicted the docblocks. `WP_Setting::encrypt()` and `WP_Setting::decrypt()` are unaffected from the outside: they catch the failure and return the original value, as documented. Code calling `WP_Setting_Encryption` directly and testing the result with `instanceof \Error` must catch `\RuntimeException` instead.

### Added

- An openssl fallback for encryption ([#12](https://github.com/bgoewert/wp-settings/issues/12)). Where sodium is unavailable, values are encrypted with AES-256-GCM — an AEAD construction equivalent to `sodium_crypto_secretbox()` — rather than encryption silently becoming impossible. openssl payloads carry a `wps.aesgcm.v1:` marker, so the two formats are told apart on read and existing sodium ciphertexts keep working untouched. Sodium remains the default wherever it is loaded. Unlike the sodium path, the openssl path generates a fresh IV per value instead of reusing the configured nonce, because IV reuse under GCM is catastrophic. A sodium payload still cannot be read on a build without sodium; move the value before migrating, or re-save it.
- `WP_Setting_Encryption::is_available()`, which reports whether either backend is present.
- `ext-sodium` and `ext-openssl` in `composer.json`'s `suggest` block, so the platform requirement is visible at install time. Neither is a hard requirement: encryption is opt-in per field and the rest of the library works without both.

### Fixed

- `WP_Setting_Encryption` no longer fatals on PHP built without sodium ([#12](https://github.com/bgoewert/wp-settings/issues/12)). The key, nonce and MAC lengths were property initialisers reading `SODIUM_CRYPTO_SECRETBOX_*`. Property initialisers are evaluated at instantiation, before any method body runs, so constructing the class raised an undefined-constant `\Error` and the class's own `extension_loaded('sodium')` guards were unreachable. The lengths are now resolved in the constructor, preferring the `SODIUM_*` constants where defined. This made the library unloadable — not merely unable to encrypt — on sodium-less runtimes such as WordPress Playground's WASM build.
- `WP_Setting::encrypt()` and `WP_Setting::decrypt()` catch `\Throwable` rather than `\Exception`, and construct `WP_Setting_Encryption` inside the `try`. An `\Error` is a `\Throwable` but not an `\Exception`, so the intended "warn and return the value unchanged" fallback was skipped and the error took down the request.

## [2.31.2] - 2026-08-07

### Fixed

- `checkbox` fields can be turned off again ([#11](https://github.com/bgoewert/wp-settings/issues/11)). `WP_Setting::save()` stored a PHP boolean, and `update_option($option, false)` does not reliably leave a usable row; `WP_Setting::add_setting()` runs `add_option()` on every admin page load, so the missing row was re-seeded with the field's default and an unchecked box came back checked on the next view. The value is now stored as the string `'1'` or `'0'`, so the row always exists. Reading is unchanged: the on state already read back as `'1'` from the database, and `'0'` is falsy in PHP and already rendered unchecked.

## [2.31.1] - 2026-08-07

### Added

- `WP_Setting::sanitize_color()` and `WP_Setting::is_valid_hex_color()`, public so a consumer can reuse or wrap the library's color validation.

### Fixed

- `color` fields now get a default `sanitize_callback` and no longer store whatever was posted ([#10](https://github.com/bgoewert/wp-settings/issues/10)). The per-type defaults in `WP_Setting::__construct()` covered `email`, `url`, `number`, `sortable`, `text`, `textarea`, `richtext` and `repeater` but not `color`, so a color field fell through `save()` with no callback and the raw `$_POST` value reached the option. A browser's `<input type="color">` only submits `#rrggbb`, but `wp-admin/options.php` writes every registered option from `$_POST`, so a crafted request could store arbitrary text in a value consumers routinely interpolate into a `<style>` block or an inline `style` attribute. Only `#rgb` and `#rrggbb` are accepted — `rgb()`, `hsl()` and named colors are rejected, matching what the control can submit — and an invalid value stores `false` rather than falling back to the field's default, consistent with how `url` and `email` already reject invalid input. Pass your own `sanitize_callback` to accept other CSS color syntaxes.
- `repeater` children declared as `type => color` are validated the same way instead of falling through to `sanitize_text_field()`, which stripped tags but left a non-color remnant of the submitted value. An invalid child value is stored as an empty string, since a row field holds a string.

## [2.31.0] - 2026-08-06

### Added

- `WP_Setting::UNLABELABLE_TYPES` and `WP_Setting::renders_labelable_control()`, which name the field types whose markup has no single control carrying the field slug. Use the method when rendering your own row headings.

### Fixed

- Settings rows now associate their visible title with the field's control ([#9](https://github.com/bgoewert/wp-settings/issues/9)). WordPress's `do_settings_fields()` wraps a row's `<th>` text in `<label for="…">` only when the field declares `label_for` in its args, and the library never set it, so every control on every screen built with it rendered a heading assistive technology could not associate with its input — a WCAG 1.3.1/4.1.2 failure on the whole screen at once (axe `label` at critical impact, plus `select-name` for `select` fields, which have no other naming source). `label_for` now defaults to the field slug, which is the id every single-control renderer emits. A consumer passing its own `label_for` keeps it, and an explicit empty string opts out, since WordPress tests the arg with `! empty()`.
- The label is deliberately skipped for the types in `WP_Setting::UNLABELABLE_TYPES`, where nothing carries the slug and a `for` would point at a nonexistent element: `radio` (one wrapped label per option, inputs have no id), `sortable`, `table`, `field_map` and `repeater` (a control per row), `advanced` and `fieldset` (children label themselves), `hidden` (registers no row), and `richtext` (TinyMCE hides the textarea holding the id). Any other type — including a custom input type falling through to the text renderer — gets the label.
- `fieldset` children no longer emit an orphan `<label for="{child slug}">` when the child is one of those types; the heading renders as plain `<th>` text instead. `advanced` children, whose `<p><strong>` heading was never associated with anything, are now wrapped in a `<label for>` when the child is labelable, with identical visual markup.

## [2.30.1] - 2026-08-04

### Removed

- `WP_Settings::__construct()` no longer creates a `{text_domain}_key` option ([#8](https://github.com/bgoewert/wp-settings/issues/8)). Nothing ever read it — `WP_Setting_Encryption` takes its key from a `wp-config.php` constant, falling back to `LOGGED_IN_KEY` — but because the option was written non-autoloaded and the constructor runs on every request, `add_option()`'s existence check cost one `SELECT` on every uncached frontend page view for the life of the site. Existing rows are left in place rather than deleted, in case a consumer has come to rely on the value; delete `{text_domain}_key` manually if you want the row gone.

## [2.30.0] - 2026-07-30

### Added

- Text-like fields (`text`, `email`, `url`, `number`, `password`) and `textarea` fields now accept a `readonly` key in `$args`, so a field can be rendered non-editable without a consumer shipping its own `<script>` to set `readOnly` after load ([#7](https://github.com/bgoewert/wp-settings/issues/7)). Boolean attributes are rendered by presence, not value: a truthy arg emits the bare `readonly` attribute and any falsy arg (`false`, `0`, `'0'`, `''`, `null`) omits it entirely, because a browser honours `readonly="0"` exactly as it honours `readonly`. The new `WP_Setting::PASSTHROUGH_BOOLEAN_INPUT_ATTRIBUTES` constant lists which attributes take this path. `disabled` is deliberately excluded: browsers drop disabled inputs from the POST body and `wp-admin/options.php` writes every registered option from `$_POST`, so a disabled field would blank its own stored option on save. `readonly` fields are uneditable in the browser only — a crafted POST can still change the value, so keep enforcing invariants in `sanitize_callback`.

## [2.29.2] - 2026-07-27

### Fixed

- Text-like fields (`text`, `email`, `url`, `number`, `password`) now render the `min`, `max`, `step`, `pattern`, `minlength`, `maxlength`, `size` and `autocomplete` keys from `$args` onto the `<input>`. These were accepted by the constructor and already whitelisted for the `wp_kses()` pass, but `render_text_value()` never emitted them, so number fields silently lost their client-side bounds and stepper granularity ([#5](https://github.com/bgoewert/wp-settings/issues/5)). Empty strings and non-scalar values are skipped, `0` is rendered, and values are escaped with `esc_attr()`. Server-side sanitization is unchanged — `min`/`max` remain browser-side hints, so keep enforcing hard bounds in a `sanitize_callback`.

## [2.29.1] - 2026-07-24

### Fixed

- `WP_Setting_Encryption` no longer emits the PHP 8.1+ `mb_strlen(): Passing null to parameter #1` deprecation. `check_key_len()`, `check_nonce_len()` and `safe_base64_decode()` now coerce their argument to a string at the point of use, so a null from an unset key/nonce constant or an empty stored option can no longer reach `mb_strlen()`/`base64_decode()`. Guarding the leaf helpers closes the paths that the v2.28.4 constant-cast fix did not cover.

## [2.29.0] - 2026-07-17

### Added

- `WP_Setting::set_text_domain( string $domain )`, a documented way to set the static `WP_Setting::$text_domain` (with the same hyphen-to-underscore normalization `WP_Settings::__construct()` applies) without constructing a full `WP_Settings` subclass.
- `WP_Setting::get()` and `WP_Setting::set()` now trigger `_doing_it_wrong()` when called while `$text_domain` is still unset, naming the likely cause (a `WP_Settings` subclass constructed only inside `if ( is_admin() )`) and the fix. Visible under `WP_DEBUG`, silent in production; the existing fallback return value is unchanged either way. This surfaces a previously-silent bug: without a set text domain, both methods read/write an unprefixed, nonexistent option key on every request outside wp-admin (frontend, REST, WP-CLI), always returning the caller's hardcoded default instead of the admin-configured value.

## [2.28.4] - 2026-07-15

### Fixed

- `WP_Setting_Encryption::get_default_key()` and `get_default_nonce()` no longer emit an `mb_strlen(): Passing null to parameter #1 ($string) of type string is deprecated` warning on PHP 8.1+ when the key/nonce constant is defined with a `null` value (e.g. `define('X_KEY', getenv('X_KEY') ?: null)`, a common "not configured yet" pattern). The constant's value is now cast to a string before being base64-decoded and length-checked.
- `WP_Settings::init()` no longer emits an `Undefined array key "name"` warning when a registered settings section omits the `name` key. It now falls back to an empty string, matching the existing `?? $key` fallback already used for `slug` on the same line.
- Added regression coverage confirming `WP_Setting::set()`/`delete()`'s existing `(string)` cast keeps the text-domain-normalization `str_replace()` call from ever receiving a `null` subject, closing out the last of the three overlapping null/undefined-index bugs reported across the 2.27.3 and 2.28.2 vendored versions in downstream consumers.

## [2.28.3] - 2026-07-14

### Fixed

- `WP_Setting::get()`, `set()`, and `delete()` no longer emit a `str_replace()`/`strpos(): Passing null to parameter #1/#3` deprecation warning on PHP 8.1+ when called with a `null` setting name (e.g. from an unset field slug). The setting name is now cast to a string on entry, matching the fix already applied to `normalize_text_domain()` in 2.28.2.

## [2.28.2] - 2026-07-09

### Fixed

- `normalize_text_domain()` no longer emits a `str_replace(): Passing null to parameter #3 ($subject) is deprecated` warning on PHP 8.1+. The text domain is now cast to a string before replacement, so calls made before a consumer sets the static `$text_domain` degrade to an empty prefix instead of tripping the deprecation.

## [2.28.1] - 2026-07-09

### Fixed

- Composer dist archives no longer ship development-only files. `tests/`, `openspec/`, `.github/`, `phpunit.xml.dist`, `composer.lock`, and internal docs (`AGENTS.md`, `CLAUDE.md`, `CONTRIBUTING.md`) are excluded via `.gitattributes` `export-ignore`, so production consumers pulling the release get a lean `vendor/` dir. `src/`, `CHANGELOG.md`, `README.md`, `LICENSE`, and `composer.json` still ship.

## [2.28.0] - 2026-07-09

### Changed

- Container fields (`advanced` and `fieldset`) now render each child through the child's own field renderer — the same path a top-level field uses — instead of a fixed set of hardcoded types. Any field type can now be nested as a child, including `repeater`, `field_map`, `radio`, `richtext`, `sortable`, and a nested `advanced`/`fieldset`; previously only `checkbox`, `text`/`email`/`url`/`number`, `textarea`, and `select` rendered and other types produced no output. A child's custom `callback` is now invoked with its `args`, matching top-level fields — this activates callbacks that were previously ignored on container children and may surface latent consumer bugs.
- Enter-to-submit now applies to all single-line text fields (`text`, `email`, `url`, `number`, `password`) at both the top level and as container children, rather than only text children of `advanced` containers.
- Top-level `advanced`/`fieldset` containers now span the full width of the settings table by reclaiming the empty label column.

### Added

- Add `hide_child_labels` arg for `fieldset` fields. When `true`, each child's label column is dropped and the control spans the full width, letting the `<legend>` serve as the group label — useful when a child's title merely repeats the legend (e.g. a lone repeater). Defaults off; existing fieldsets are unchanged.

## [2.27.3] - 2026-05-26

### Fixed

- Use plugin version for admin asset cache busting instead of hardcoded `1.0.0`.

## [2.27.2] - 2026-05-08

### Fixed

- Fix `numbered_rows` repeater row number circles overlapping the first cell. Replaced unreliable `position: absolute` on `<tr>::before` (CSS spec does not guarantee `<tr>` establishes a containing block) with an explicit `<td class="wps-repeater-row-number">` cell and a matching empty `<th>` in the header row. CSS counters now render via `td::before`, which is a proper table layout participant and behaves consistently across all browsers.

## [2.27.1] - 2026-05-04

### Fixed

- Extend `reset_button` arg support to `text` fields (e.g. subject line inputs). The underlying JS sets `.value` on the element by ID, which works identically for `<input>` and `<textarea>`.

## [2.27.0] - 2026-05-04

### Added

- Add `merge_tags` arg (associative array of `'{placeholder}' => 'Label'`) to `text`, `textarea`, and `richtext` fields. Renders a scoped "Insert Merge Tag ▼" dropdown button after the field that inserts the selected tag at the cursor. For `richtext`, uses `tinymce.get(id).insertContent()` when the visual editor is active, with a textarea cursor-insertion fallback. Each dropdown is self-contained with no shared state between fields.
- Add `reset_button` arg (boolean) to `textarea` and `richtext` fields. When `true` and `default_value` is set, renders a "Reset to Default" button that restores the field to its default value. For `richtext`, uses `tinymce.get(id).setContent()` when the visual editor is active, with a direct `textarea.value` fallback.
- Add error alert email notifications to the built-in logging tab. Two new settings — **Notify on Errors** (checkbox) and **Alert Email Addresses** (comma-separated text, shown when the checkbox is enabled) — appear automatically when the logging feature is enabled. When an error-level entry is logged, `wp_mail()` fires for each configured address.

## [2.26.1] - 2026-05-01

### Fixed

- Fix `richtext` editor height not auto-expanding to fit content. Removed `teeny` mode (which excluded the `wpautoresize` plugin) and enabled `wp_autoresize_on` with `add_unload_trigger: false` so the editor grows to fit its content without clipping.

## [2.26.0] - 2026-05-01

### Added

- Add `richtext` field type backed by `wp_editor()`. Sanitizes with `wp_kses_post()` so HTML tags are preserved. Useful for formatted text fields such as email body templates.

## [2.25.0] - 2026-05-01

### Added

- Add `preserve_percent_encoded` (boolean, default `false`) flag to repeater child config. When `true`, the repeater bypasses WP's `sanitize_text_field` / `sanitize_textarea_field` for that child and uses an in-package sanitizer that strips HTML but preserves percent-encoded sequences (`%XX`). Required for fields that legitimately hold SOQL `LIKE` patterns, URL-encoded values, MySQL collation names, etc. — the WP defaults silently strip any `%[a-f0-9]{2}` pattern, which destroyed values like `%DA2%`. Flag is opt-in to avoid changing default sanitization behavior for existing consumers.

## [2.24.0] - 2026-04-28

### Added

- Add `WP_Setting::make()` named static constructor as a readable alternative to the positional `new WP_Setting(...)` call; promotes `$args` before display params and surfaces `$sanitize_callback` as a first-class parameter instead of requiring it inside `$args`

## [2.23.0] - 2026-04-28

### Added

- Add `args['numbered_rows']` boolean (default `false`) to repeater fields — when `true`, each row gets a CSS counter badge (1, 2, 3…) via `wps-repeater-numbered` wrapper class; counters re-number automatically as rows are added or removed without any JS changes

## [2.22.3] - 2026-04-28

### Fixed

- Fix Logging tab silently dropped when child class calls `parent::__construct()` before defining its own sections/settings — `append_logging_definitions()` now defers to `admin_init` at priority 0 (before `init()` at priority 10) via `_append_logging_definitions_once()`, so the child constructor always fully runs first; a guard prevents double-append if called explicitly

## [2.22.2] - 2026-04-28

### Fixed

- Fix `menu_page_callback()` saving all tabs on every submit — settings from other tabs were not in POST data, causing text fields to be overwritten with null and checkboxes forced to false; now only saves settings belonging to the active tab via new `save_tab_settings(string $tab): void` protected method (overridable by child classes)

## [2.22.1] - 2026-04-28

### Fixed

- Fix fieldset children rendering twice — `init()` was passing `true` to `$child->init()`, causing each child to register as a standalone `add_settings_field()` row in addition to rendering inside the fieldset box; changed to `false` to match `advanced` behavior

## [2.22.0] - 2026-04-28

### Added

- Add `rows`, `class`, and `placeholder` args passthrough to `render_textarea_value()` for standalone textarea fields, matching behavior already present in advanced/fieldset child renderers
- Make `<details>` inline style in `init_advanced()` overridable via `args['style']`; defaults to previous style (removing the forced `margin-top: 20px` when inside a WordPress settings table `<td>`)

### Fixed

- Fix `WP_Setting::save()` missing `case 'fieldset'` branch — fieldset children were silently discarded; now mirrors the `case 'advanced'` behavior and iterates children recursively

## [2.21.1] - 2026-04-27

### Fixed

- Fix prefix mismatch: normalize `text_domain` in `WP_Setting` constructor so `$this->slug` matches the key used by `WP_Setting::get()`/`set()`. Settings with hyphenated text domains (e.g. `my-plugin`) were silently writing to a different option key than programmatic reads, making form-saved values invisible to `::get('short_name')` callers.
- Add `WP_Setting::migrate_option_prefix()` to rename existing option rows from the old hyphenated prefix to the normalized underscore prefix on plugin upgrade.

## [2.21.0] - 2026-04-16

### Added

- Add `WP_Setting::delete()` static method to complete the get/set/delete CRUD API, with the same slug prefix normalization as `get()` and `set()`

## [2.20.1] - 2026-04-16

### Fixed

- Fix `WP_Setting::set()` missing `$autoload` parameter — pass it through to `update_option()` (WP 6.4+)

## [2.20.0] - 2026-04-16

### Added

- Add `autoload` parameter to `WP_Setting` to control whether options are loaded on every WordPress page load, with guidance on when to enable or disable autoloading

### Changed

- Disable autoloading for the encryption key option, as it is only needed on settings pages

## [2.19.0] - 2026-04-14

### Added

- Add repeater field type for dynamic lists of structured rows with configurable child fields (text, email, url, number, textarea, select)

### Fixed

- Fix password field "Show" button not working on custom admin pages by enqueuing the `wp-auth` script when password fields are present

## [2.18.1] - 2026-03-20

### Fixed

- Preserve the normal Enter-to-save behavior for text-like child fields inside advanced settings groups

## [2.18.0] - 2026-03-20

### Added

- Add optional built-in logging support with a `Logging` settings tab, plugin log files, retention controls, and an admin log viewer

## [2.17.3] - 2026-03-20

### Fixed

- Prevent advanced child settings from being registered and rendered as standalone rows beneath the collapsible advanced field

## [2.17.2] - 2026-03-16

### Fixed

- Fixed greedy regex in wp-config.php constant parsing that captured closing punctuation, causing base64 decoding to fail and wrong encryption key bytes to be used
- Fixed auto-generated encryption constants being appended after `require_once wp-settings.php` in wp-config.php, making them unavailable during WordPress execution

## [2.17.0] - 2026-03-04

### Added

- Support newlines in textarea fields by preserving line breaks on save and render

## [2.16.7] - 2026-03-02

### Fixed

- Add class existence guards to prevent fatal redeclaration errors when multiple plugins load the library

## [2.16.6] - 2026-03-02

### Changed

- Improve constructor flexibility with null parameter support and input validation
- Normalize text domain to use underscores consistently for option names

### Fixed

- Handle associative array keys when getting default tab
- Display settings saved notification

## [2.16.3] - 2026-02-13

### Fixed

- Filter out field map rows where either destination or source field is empty during save

## [2.16.2] - 2026-02-13

### Fixed

- Fix has_settings_for_tab to check both prefixed and non-prefixed page values

## [2.16.1] - 2026-02-13

### Fixed

- Only render form wrapper and submit button when tab has actual settings fields, not just section callbacks

## [2.16.0] - 2026-02-12

### Added

- Support rendering both table and settings sections on the same tab

## [2.15.1] - 2026-02-12

### Fixed

- Change default footer_text behavior to show empty string instead of WordPress default on plugin settings pages

## [2.15.0] - 2026-02-12

### Added

- Add optional `version` property to display plugin version in admin footer on settings pages (right side)
- Add optional `footer_text` property to customize left side of admin footer on settings pages

## [2.14.0] - 2026-02-03

### Added

- Add `show_status_toggle` option for WP_Settings_Table to display Enable/Disable buttons on individual rows

## [2.13.0] - 2026-02-03

### Added

- Add `field_map` field type for dynamic field mapping with add/remove rows and merge tag selector

### Fixed

- Fix checkbox fields not being checked when editing table rows in modals

## [2.11.0] - 2026-02-02

### Added

- Add `fieldset` field type for visual grouping of child settings
- Add support for `fieldset` and `advanced` fields in table modals

## [2.10.0] - 2026-01-30

### Added

- `collapsed` parameter for advanced fields (defaults to `true`, set to `false` to expand by default)

## [2.9.0] - 2026-01-30

### Added

- Base badge style for sortable fields (`.wps-sortable-badge`)

## [2.8.0] - 2026-01-30

### Added

- `table` field type for embedding WP_Settings_Table instances within sections alongside other settings
- `item_meta` option for sortable fields to add custom classes and badges to individual items

## [2.7.0] - 2026-01-29

### Added

- Support for using array keys as section slugs (backward compatible with explicit `slug` property)

## [2.6.0] - 2026-01-29

### Added

- Conditional field visibility via `conditions` key in args array with operators: `equals`, `not_equals`, `in`, `not_in`, `empty`, `not_empty`

## [2.5.0] - 2026-01-21

### Added

- `sortable` field type with drag-and-drop ordering and numeric position inputs
- Sortable field admin JS/CSS assets loaded only when sortable fields are present

## [2.4.0] - 2026-01-21

### Added

- `WP_Settings_Table` for reusable settings tables with modal CRUD, bulk actions, and inline status toggles
- Shared admin JS/CSS assets for table UI interactions
- Unbound field rendering and sanitization helpers on `WP_Setting` to reuse field definitions in tables
- Tests covering settings table CRUD and bulk actions

## [2.3.0] - 2026-01-21

### Added

- Validation helper methods: `WP_Setting::is_valid_url()`, `WP_Setting::is_valid_email()`, `WP_Setting::is_not_empty()` for validating user input
- Sanitization helper methods: `WP_Setting::sanitize_url()`, `WP_Setting::sanitize_email()`, `WP_Setting::sanitize_text()` for sanitizing and validating values
- Default automatic sanitization for field types:
  - `email` fields now automatically sanitize and validate email addresses
  - `url` fields now automatically sanitize and validate URLs
  - `number` fields now automatically validate numeric values
  - `text` and `textarea` fields now automatically sanitize text input (strip tags, trim whitespace)
- Custom `sanitize_callback` in args array can override default sanitization for any field type

## [2.2.0] - 2026-01-12

### Added

- Flexible constructor: `WP_Settings::__construct()` now accepts either a plugin data array OR a simple text domain string for easier initialization
- Automatic duplicate menu prevention: `WP_Settings::admin_menu()` now checks global `$submenu` before registering to prevent duplicate menu entries when multiple classes extend `WP_Settings`

### Fixed

- Encryption keys and nonces are now properly base64-decoded when retrieved from wp-config constants using a safe decoder that maintains backward compatibility with non-encoded values

## [2.1.0] - 2026-01-08

### Added

- Support sanitize callbacks in WP_Setting via `sanitize_callback` key in args array

## [2.0.0] - 2026-01-08

### Removed

- **Breaking:** Remove `$text_domain` parameter from `WP_Setting` constructor. Text domain is now set automatically via static property by `WP_Settings` parent class.

## [1.1.2] - 2025-12-15

### Fixed

- Standalone checkbox fields not sending unchecked values (added hidden field that advanced field checkboxes already had)
- Password fields being overwritten with empty values on save (only saves when new value provided)
- Password fields displaying saved values in plain text (now shows placeholder for security)
- Advanced field parent's save() method to automatically save all child settings
- Array values causing errors in text inputs (added safety check)
- Required attribute showing on password fields with existing values

## [1.1.1] - 2025-12-07

### Fixed

- Advanced field child settings not saving (children were not registered with WordPress)

## [1.1.0] - 2025-12-01

### Added

- Add `hidden` field type for storing values without rendering table rows
- Add `advanced` field type with collapsible `<details>` section containing child settings

## [1.0.0] - 2025-11-15

### Added

- Add basic settings for text, textarea, checkbox, radio, and select
- Add basic encryption/decryption based on existing default wp-config salts
