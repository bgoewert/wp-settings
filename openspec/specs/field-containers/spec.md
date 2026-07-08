# field-containers Specification

## Purpose
TBD - created by archiving change container-child-delegation. Update Purpose after archive.
## Requirements
### Requirement: Container fields render any child field type

The `advanced` and `fieldset` container field types SHALL render each child field by delegating to that child's own field renderer (the same rendering path used for a top-level field of that type), rather than a fixed set of hardcoded child types. As a result, a container SHALL be able to nest any supported field type as a child, including `repeater`, `field_map`, `radio`, `richtext`, `sortable`, and a nested `advanced` or `fieldset`. A child of a type the container cannot render SHALL NOT occur — every registered field type is renderable as a child.

#### Scenario: Repeater nested in a container renders

- **WHEN** an `advanced` (or `fieldset`) field has a `repeater` child
- **THEN** the repeater renders fully inside the container (its rows, add/remove controls, and description)
- **AND** the repeater's value round-trips through save exactly as it does at the top level

#### Scenario: Nested container renders its own children

- **WHEN** an `advanced` field has a child that is itself an `advanced` or `fieldset`
- **THEN** the nested container and its children render

#### Scenario: Previously unsupported simple types render

- **WHEN** a container has a `radio`, `richtext`, or `field_map` child
- **THEN** that child renders (types that previously produced no output)

### Requirement: Container child titles and descriptions are preserved

When rendering a child, a container SHALL display the child's title as a heading for that child and SHALL render the child's description, so nested fields remain labeled and documented.

#### Scenario: Child title and description shown

- **WHEN** a container renders a child that has a title and a description
- **THEN** the child's title is shown as its heading
- **AND** the child's description is shown

### Requirement: Existing simple-field containers remain backward compatible

Delegating child rendering SHALL NOT change how child fields are registered, stored, or sanitized, and SHALL preserve the save behavior of existing child types. In particular, a `checkbox` child SHALL continue to submit a value when unchecked (via its hidden companion input).

#### Scenario: Unchecked checkbox child still saves

- **WHEN** a container has a `checkbox` child that the user leaves unchecked and saves
- **THEN** the checkbox persists as unchecked (a `0` value is submitted)

#### Scenario: Child options are registered independently of rendering

- **WHEN** a container's children are set up
- **THEN** each child's option is registered and sanitized regardless of the rendering change

