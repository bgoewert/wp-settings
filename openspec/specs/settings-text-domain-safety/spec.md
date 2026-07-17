# settings-text-domain-safety Specification

## Purpose
TBD - created by archiving change safeguard-text-domain-outside-admin. Update Purpose after archive.
## Requirements
### Requirement: Misuse Detection for Unset Text Domain
`WP_Setting::get()` and `WP_Setting::set()` SHALL trigger `_doing_it_wrong()` when called while `WP_Setting::$text_domain` is empty, while still returning the same fallback value they return today (the provided default for `get()`, or performing the unprefixed write for `set()`).

#### Scenario: get() called before text domain is set
- **WHEN** `WP_Setting::get( 'some_setting', 'default_value' )` is called
- **AND** `WP_Setting::$text_domain` has not been set (via `WP_Settings` construction or `set_text_domain()`)
- **AND** `WP_DEBUG` is enabled
- **THEN** `_doing_it_wrong()` SHALL be triggered, naming the missing text domain as the cause
- **AND** the method SHALL still return `'default_value'` (unchanged existing fallback behavior)

#### Scenario: No notice in production
- **WHEN** the same call is made
- **AND** `WP_DEBUG` is disabled
- **THEN** no notice, warning, or error SHALL be emitted or logged
- **AND** the return value SHALL be unchanged from today's behavior

#### Scenario: No notice once the domain is set
- **WHEN** `WP_Setting::$text_domain` has been set (by either mechanism)
- **AND** `WP_Setting::get()` or `::set()` is subsequently called
- **THEN** no `_doing_it_wrong()` notice SHALL be triggered, regardless of `WP_DEBUG`

### Requirement: Explicit Text Domain Setter
`WP_Setting::set_text_domain( string $domain )` SHALL be available as a public static method that normalizes and assigns `WP_Setting::$text_domain`, equivalent to the normalization `WP_Settings::__construct()` performs, without requiring construction of a `WP_Settings` subclass.

#### Scenario: Setting the domain directly
- **WHEN** `WP_Setting::set_text_domain( 'my-plugin' )` is called
- **THEN** `WP_Setting::$text_domain` SHALL be set to the normalized form of `'my-plugin'`
- **AND** subsequent `WP_Setting::get()`/`::set()` calls SHALL use that domain for option-key prefixing, identical to if a `WP_Settings` subclass had been constructed with the same domain

#### Scenario: Setter is idempotent with subsequent WP_Settings construction
- **WHEN** `WP_Setting::set_text_domain( 'my-plugin' )` has already been called
- **AND** a `WP_Settings` subclass is later constructed with the same text domain
- **THEN** `WP_Setting::$text_domain` SHALL remain correctly set to that domain (no conflict or error)
