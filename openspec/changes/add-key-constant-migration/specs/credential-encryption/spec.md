## MODIFIED Requirements

### Requirement: Key material is resolved from the environment, never from a config file
The library SHALL resolve its encryption key and nonce in this order: a defined PHP constant, then an environment variable of the same name, then the WordPress authentication salts. It SHALL NOT read key material by parsing the text of a configuration file. It SHALL NOT write to any configuration file, except to delete, when an administrator requests the key-constant migration, the `define()` lines for its own key and nonce constants.

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

## ADDED Requirements

### Requirement: An administrator can retire the legacy key constants from a notice
While a settings page's key or nonce constant is defined, the library SHALL show users who can manage options a notice offering to migrate its encrypted fields to the WordPress salts, stating that rotating the salts afterward makes those fields unreadable. On request it SHALL decrypt every encrypted field with the constants and stage it re-encrypted under the salts, then delete the constants' `define()` lines from `wp-config.php` when the file is writable and file modifications are allowed, or otherwise show the lines to delete with their values elided. Stored values SHALL stay untouched until a request resolves the key without the constants; that request SHALL swap each staged value in only after it decrypts under the key the request resolves. The migration SHALL NOT be offered on multisite.

#### Scenario: wp-config.php is writable
- **WHEN** an administrator presses the button and every encrypted field decrypts with the constants
- **THEN** both `define()` lines are deleted, the next request stores the salt-encrypted values, and a read returns the original plaintext

#### Scenario: wp-config.php cannot be edited
- **WHEN** the file is not writable, file modifications are disallowed, or a `define()` is not in the form the library wrote
- **THEN** the file is left unchanged, the notice shows the lines to delete without their values, and stored values keep working under the constants until the lines are gone

#### Scenario: A field cannot be decrypted with the constants
- **WHEN** any encrypted field fails to decrypt
- **THEN** nothing is staged and nothing is deleted, and the notice names the field

#### Scenario: A field is re-entered while the migration waits
- **WHEN** an encrypted field is saved after staging and before the constants are removed
- **THEN** the staged copy is replaced with the new value, so the swap does not roll it back

#### Scenario: A front-end request runs first after the lines are removed
- **WHEN** an encrypted field is read with decryption before any admin page has loaded without the constants
- **THEN** the staged values are swapped in before the read, and it returns the plaintext

#### Scenario: The site is a multisite network
- **WHEN** the constants are defined on a multisite install
- **THEN** no notice is shown and the migration request is refused
