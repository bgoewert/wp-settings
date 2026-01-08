# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on [Common Changelog](https://common-changelog.org/), and this project adheres to [Semantic Versioning](https://semver.org/).

## 2.0.0 - 2026-01-08

- **Breaking:** Remove `$text_domain` parameter from `WP_Setting` constructor. Text domain is now set automatically via static property by `WP_Settings` parent class.

## 1.1.2 - 2025-12-15

- Fixed: Standalone checkbox fields not sending unchecked values (added hidden field that advanced field checkboxes already had)
- Fixed: Password fields being overwritten with empty values on save (only saves when new value provided)
- Fixed: Password fields displaying saved values in plain text (now shows placeholder for security)
- Fixed: Advanced field parent's save() method to automatically save all child settings
- Fixed: Array values causing errors in text inputs (added safety check)
- Fixed: Required attribute showing on password fields with existing values

## 1.1.1 - 2025-12-07

- Fixed: Advanced field child settings not saving (children were not registered with WordPress)

## 1.1.0 - 2025-12-01

- Added: `hidden` field type for storing values without rendering table rows
- Added: `advanced` field type with collapsible `<details>` section containing child settings

## 1.0.0 - 2025-11-15

- Added: Basic settings for text, textarea, checkbox, radio, and select
- Added: Basic encryption/decryption based on existing default wp-config salts
