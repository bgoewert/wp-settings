## ADDED Requirements

### Requirement: A disabled field renders inert

A field declaring a truthy `disabled` argument SHALL render its controls inside a disabled `<fieldset>`, so that no control it renders can be edited or submitted. An `advanced` or `fieldset` container SHALL ignore the argument.

#### Scenario: A disabled checkbox

- **WHEN** a checkbox field stored as on declares `disabled`
- **THEN** the checkbox renders checked and disabled

### Requirement: A disabled field keeps its stored value

While a field declares `disabled`, its registered sanitizer SHALL return the stored value regardless of the submitted value, the field's own `sanitize_callback` SHALL NOT change it, and the library's save SHALL NOT write it.

#### Scenario: The settings are saved

- **WHEN** the tab holding a disabled checkbox stored as on is saved through options.php
- **THEN** the option is still on

#### Scenario: A forged POST

- **WHEN** a request submits a value for a disabled field
- **THEN** the stored value is unchanged
