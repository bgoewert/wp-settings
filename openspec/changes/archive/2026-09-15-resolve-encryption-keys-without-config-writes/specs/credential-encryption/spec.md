## Purpose

Defines how the library resolves the secret material used to encrypt stored credentials, how it reports a value it can no longer read, and how a site moves its stored values from one key to another without losing them.

## ADDED Requirements

### Requirement: Key material is resolved from the environment, never from a config file
The library SHALL resolve its encryption key and nonce in this order: a defined PHP constant, then an environment variable of the same name, then the WordPress authentication salts. It SHALL NOT read key material by parsing the text of a configuration file, and SHALL NOT write to any configuration file.

#### Scenario: A dedicated constant is defined
- **WHEN** the key constant is defined and non-empty
- **THEN** its value is used as the key, and no configuration file is read or written

#### Scenario: The value is supplied as an environment variable
- **WHEN** the key constant is not defined but an environment variable of the same name is set and non-empty
- **THEN** that value is used as the key

#### Scenario: Neither a constant nor an environment variable is present
- **WHEN** no constant and no environment variable supply the key
- **THEN** the WordPress authentication salts are used, and the configuration file is left untouched even when it is writable

#### Scenario: A constant appears only as unexecuted text in a configuration file
- **WHEN** a configuration file contains a matching `define()` that was never executed, so the constant is not defined at runtime
- **THEN** that text is ignored and resolution continues to the environment variable and then the salts

### Requirement: A resolved key always yields a string
The library SHALL resolve a string value for both key and nonce under every branch of the resolution order, including when every source is absent or empty.

#### Scenario: Every source is absent
- **WHEN** no constant, no environment variable, and no authentication salt supplies a value
- **THEN** key and nonce resolve to strings, and no length or string function receives `null`

### Requirement: A value encrypted under a different key is reported as a key change
The library SHALL record a non-reversible fingerprint of the key in use when it writes an encrypted value, and SHALL distinguish a value written under a different key from a value that is merely wrong or corrupt. A settings screen rendering such a value SHALL state that the encryption key changed and the value must be re-entered.

#### Scenario: The salts were rotated after values were stored
- **WHEN** a stored value cannot be decrypted and its recorded fingerprint does not match the current key
- **THEN** the screen reports that the encryption key changed and the value must be re-entered, rather than reporting an authentication or credential failure

#### Scenario: The key is unchanged but the payload is corrupt
- **WHEN** a stored value cannot be decrypted and its recorded fingerprint matches the current key
- **THEN** the failure is reported as a corrupt or tampered value, not as a key change

#### Scenario: A value predates fingerprinting
- **WHEN** a stored value carries no fingerprint
- **THEN** it is decrypted as before, and a successful read records the current fingerprint

### Requirement: Stored values can be rewrapped from a legacy key in one pass
The library SHALL expose a public routine that a consuming plugin calls from its own upgrade hook, supplying the legacy key material and the list of setting names to migrate. The routine SHALL decrypt each named setting with the legacy material and re-encrypt it under current resolution. It SHALL be safe to run more than once, SHALL report per-setting success or failure, and SHALL leave a value untouched when it cannot be decrypted with the legacy material.

#### Scenario: A site moves from a custom constant to the current resolution
- **WHEN** the routine runs with the legacy constant's value as the legacy key material
- **THEN** every named setting readable under that material is re-encrypted under the current key, and a later read returns the original plaintext

#### Scenario: The routine runs a second time
- **WHEN** the routine runs again after a successful pass
- **THEN** already-rewrapped values are recognized as current and left as they are, and no value is double-encrypted

#### Scenario: One setting cannot be read with the legacy material
- **WHEN** a single setting fails to decrypt under the legacy key
- **THEN** that setting is left unchanged and reported as a failure, and the remaining settings are still rewrapped

#### Scenario: The upgrade hook runs on a site that never used a legacy key
- **WHEN** the routine runs and no stored value decrypts under the supplied legacy material
- **THEN** no stored value is modified and the result reports that nothing was rewrapped
