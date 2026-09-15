## ADDED Requirements

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
