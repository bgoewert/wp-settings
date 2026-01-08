# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on [Common Changelog](https://common-changelog.org/), and this project adheres to [Semantic Versioning](https://semver.org/).

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
