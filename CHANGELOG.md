# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## Unreleased

### 1.1.2

- Fixed standalone checkbox fields not sending unchecked values (added hidden field that advanced field checkboxes already had).
- Fixed password fields being overwritten with empty values on save (only saves when new value provided).
- Fixed password fields displaying saved values in plain text (now shows placeholder for security).
- Fixed advanced field parent's save() method to automatically save all child settings.
- Fixed array values causing errors in text inputs (added safety check).
- Fixed required attribute showing on password fields with existing values.

### 1.1.1

- Fixed advanced field child settings not saving (children were not registered with WordPress).

### 1.1.0

- Added `hidden` field type for storing values without rendering table rows.
- Added `advanced` field type with collapsible `<details>` section containing child settings.

### 1.0.0

- Added basic settings for text, textarea, checkbox, radio, and select.
- Added basic encryption/decrpytion based on existing default wp-config salts.

## Released
