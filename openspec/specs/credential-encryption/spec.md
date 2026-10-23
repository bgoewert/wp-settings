# credential-encryption Specification

## Purpose
Defines how the library resolves the secret material used to encrypt stored credentials, how it reports a value it can no longer read, and how a site moves its stored values from one key to another without losing them.

## Requirements

### Requirement: Key material is resolved from the environment, never from a config file
The library SHALL resolve its encryption key and nonce in this order: an environment variable, then a defined PHP constant of the same name, then the WordPress authentication salts. It SHALL NOT read key material by parsing the text of a configuration file. It SHALL NOT write to any configuration file, except to delete, when an administrator requests the key-constant migration, the `define()` lines for its own key and nonce constants.

#### Scenario: A dedicated constant is defined
- **WHEN** the key constant is defined and non-empty, and no environment variable of the same name is set
- **THEN** its value is used as the key, and no configuration file is read or written

#### Scenario: The value is supplied as an environment variable
- **WHEN** an environment variable named for the key is set and non-empty
- **THEN** that value is used as the key, whether or not a constant of the same name is defined

#### Scenario: Neither a constant nor an environment variable is present
- **WHEN** no constant and no environment variable supply the key
- **THEN** the WordPress authentication salts are used, and the configuration file is left untouched even when it is writable

#### Scenario: A constant appears only as unexecuted text in a configuration file
- **WHEN** a configuration file contains a matching `define()` that was never executed, so the constant is not defined at runtime
- **THEN** that text is ignored and resolution continues to the salts

### Requirement: A constant that disagrees with the environment is reported
When the environment variable and the constant for the key or nonce are both set and resolve to different values, the library SHALL log the conflict once, without either value, and SHALL show users who can manage options an admin notice naming the variable and stating that the environment variable is used.

#### Scenario: A stale define() shadows a deploy-managed key
- **WHEN** the key's environment variable and constant are both set to different values
- **THEN** the environment variable is used, one log entry names the key, and the notice names it on every admin page until the two agree or one is removed

#### Scenario: The constant was copied from the environment
- **WHEN** the environment variable and the constant hold the same value
- **THEN** nothing is logged and no notice is shown

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

### Requirement: A field can declare that its value is encrypted
A field declaration SHALL accept an `encrypted` flag. A field that declares it SHALL have its submitted value sanitized by the field's own rules first and stored ciphered, and SHALL render the deciphered value. A field that does not declare it SHALL be stored and rendered unchanged. Storing a value already written under the current key SHALL leave it as it is, so a resave cannot encrypt it twice.

#### Scenario: A declared field is saved
- **WHEN** a value is written to a setting whose field declares `encrypted`
- **THEN** the sanitized value is stored ciphered, and reading the stored option directly does not return the plaintext

#### Scenario: A declared field is rendered
- **WHEN** the field renders a stored value
- **THEN** the control carries the deciphered value, never the ciphertext

#### Scenario: The stored ciphertext is written back
- **WHEN** the value already stored under the current key is saved again
- **THEN** it is stored unchanged rather than encrypted a second time

#### Scenario: A field does not declare it
- **WHEN** a value is written to a setting whose field omits `encrypted`
- **THEN** it is stored and rendered exactly as given

#### Scenario: An empty value is saved
- **WHEN** an empty value is written to a declared field
- **THEN** it is stored empty rather than as the ciphertext of an empty string

### Requirement: A declared field renders the key-change notice itself
A field declaring `encrypted` SHALL render the message from the key-change reporting requirement in place of a value it cannot decipher, and SHALL render its control empty rather than showing the ciphertext.

#### Scenario: The stored value was written under another key
- **WHEN** a declared field renders a value that will not decipher
- **THEN** the key-change message is shown and the control is empty

### Requirement: An administrator can retire the legacy key constants from a notice
While a settings page's key or nonce constant is defined, the library SHALL show users who can manage options a notice offering to migrate its encrypted fields to the WordPress salts, stating that rotating the salts afterward makes those fields unreadable. The migration SHALL NOT be offered on multisite.

#### Scenario: The site is a multisite network
- **WHEN** the constants are defined on a multisite install
- **THEN** no notice is shown and the migration request is refused

### Requirement: A migration request stages the fields and removes the constants
On request the library SHALL decrypt every encrypted field with the constants and stage it re-encrypted under the salts, then delete the constants' `define()` lines from `wp-config.php` when the file is writable and file modifications are allowed, or otherwise show the lines to delete with their values elided.

#### Scenario: wp-config.php is writable
- **WHEN** an administrator presses the button and every encrypted field decrypts with the constants
- **THEN** both `define()` lines are deleted, the next request stores the salt-encrypted values, and a read returns the original plaintext

#### Scenario: wp-config.php cannot be edited
- **WHEN** the file is not writable, file modifications are disallowed, or a `define()` is not in the form the library wrote
- **THEN** the file is left unchanged, the notice shows the lines to delete without their values, and stored values keep working under the constants until the lines are gone

#### Scenario: A field cannot be decrypted with the constants
- **WHEN** any encrypted field fails to decrypt
- **THEN** nothing is staged and nothing is deleted, and the notice names the field

### Requirement: Staged values replace stored ones only once the constants are gone
Stored values SHALL stay untouched until a request resolves the key without the constants. That request SHALL swap each staged value in only after it decrypts under the key the request resolves.

#### Scenario: A field is re-entered while the migration waits
- **WHEN** an encrypted field is saved after staging and before the constants are removed
- **THEN** the staged copy is replaced with the new value, so the swap does not roll it back

#### Scenario: A front-end request runs first after the lines are removed
- **WHEN** an encrypted field is read with decryption before any admin page has loaded without the constants
- **THEN** the staged values are swapped in before the read, and it returns the plaintext
