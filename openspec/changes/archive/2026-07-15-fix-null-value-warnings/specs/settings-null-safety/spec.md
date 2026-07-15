## ADDED Requirements

### Requirement: Encryption defaults never pass null to string-length functions
`WP_Setting_Encryption` SHALL resolve a string (never `null`) for the default key and default
nonce, even when the underlying key/nonce constant or stored option is unset, so that
`mb_strlen()`/`mb_substr()` are never invoked with a `null` value.

#### Scenario: Key constant is not defined
- **WHEN** `WP_Setting_Encryption` is instantiated and its key constant has not been defined
  (no `wp-config.php` constant, no stored fallback option)
- **THEN** `get_default_key()` returns a string (not `null`), and no PHP deprecation notice is
  emitted when the key length is checked

#### Scenario: Nonce constant is not defined
- **WHEN** `WP_Setting_Encryption` is instantiated and its nonce constant has not been defined
- **THEN** `get_default_nonce()` returns a string (not `null`), and no PHP deprecation notice is
  emitted when the nonce length is checked

### Requirement: Settings sections without a `name` key do not warn
`WP_Settings::init()` SHALL use a safe fallback when a registered section omits the `name` key,
consistent with the existing fallback already used for the `slug` key on the same line.

#### Scenario: Section registered without a `name` key
- **WHEN** a settings section is registered with only a `slug` (or no `slug`/`name` at all) and
  `init()` runs
- **THEN** `add_settings_section()` is called with a string value (not an undefined array key
  read), and no "Undefined array key" warning is emitted

### Requirement: Setting key normalization never receives a null subject
`WP_Setting::set()` and `WP_Setting::delete()` SHALL guarantee the setting name passed to
`str_replace()` during text-domain normalization is always a string, never `null`.

#### Scenario: Setting name normalization with a hyphenated text domain
- **WHEN** `WP_Setting::set()` or `WP_Setting::delete()` is called with a setting name and the
  registered text domain contains a hyphen (triggering domain normalization)
- **THEN** the normalization's `str_replace()` call receives a string subject and no PHP
  deprecation notice is emitted, regardless of the setting name's original value
