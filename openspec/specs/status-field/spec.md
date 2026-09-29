# status-field Specification

## Purpose
Lets a settings row report a derived value — a connection state, a last-sync time — as text in the settings table, carrying `actions` like any field, while storing nothing.

## Requirements

### Requirement: A status field renders a derived value as text

A `status` field SHALL render its `value` argument as text in the settings row's value column, followed by its description. When `value` is callable it SHALL be called when the row renders and its return value rendered. The row heading SHALL NOT be a `<label>`.

#### Scenario: A string value

- **WHEN** a status field declares `value` as "Connected"
- **THEN** the row reads "Connected" with no input in it

#### Scenario: A callable value

- **WHEN** a status field declares `value` as a callable
- **THEN** the callable runs when the row renders, not when the field is constructed

### Requirement: A status field stores nothing

A `status` field SHALL NOT seed an option, register a setting, or write an option when the settings are saved.

#### Scenario: The settings are saved

- **WHEN** the tab holding a status field is saved
- **THEN** no option exists under the status field's slug

### Requirement: A status field carries actions

A `status` field SHALL render its `actions` as any field does, beside the value unless `actions_position` is `below`.

#### Scenario: A status field declares Test connection

- **WHEN** a status field declares a Test connection action
- **THEN** the button renders beside the value, named "Test connection {title}"
