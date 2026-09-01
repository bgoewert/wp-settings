## ADDED Requirements

### Requirement: A condition resolves to the input its controlling field renders under

A condition's `field` SHALL resolve to the `name` the controlling field renders under in the form being evaluated, whether the condition was written with the field's declared shorthand name or with its prefixed option slug. Resolution SHALL be idempotent: a reference that already names an input SHALL come back unchanged rather than carrying the prefix twice. The same resolved name SHALL be used both for the emitted `data-conditions` and for the list of controlling fields the browser binds change listeners to, so that a condition which decides the initial state also decides every later one.

Fields rendered by a `WP_Settings_Table` modal render under their bare declared name and are held outside the page's settings, so conditions between them SHALL continue to match on that name.

#### Scenario: Condition written with the declared field name

- **WHEN** a setting declares `'conditions' => array(array('field' => 'provider', ...))` and the controlling field renders as `name="myplugin_provider"`
- **THEN** the emitted `data-conditions` names `myplugin_provider`
- **AND** the change listener is bound to `myplugin_provider`

#### Scenario: Condition written with the option slug

- **WHEN** the same condition is written as `'field' => 'myplugin_provider'`
- **THEN** the reference is left alone rather than prefixed a second time

#### Scenario: Condition inside a table modal

- **WHEN** a `WP_Settings_Table` field declares a condition naming a sibling field of that table
- **THEN** the reference stays the bare declared name, which is what the modal renders

### Requirement: A field is hidden when its conditions are not met

A field declaring `conditions` SHALL be visible only while every condition holds, combined with AND logic across entries, and SHALL support the `equals`, `not_equals`, `in`, `not_in`, `empty` and `not_empty` operators. Visibility SHALL be re-evaluated when a controlling field changes, not only at page load. Hiding SHALL be presentational: a hidden field remains in the form and still submits its value.

#### Scenario: Controlling value changes while the admin works

- **WHEN** the admin changes a controlling field to a value that satisfies a dependent field's conditions
- **THEN** that field becomes visible without a page reload
- **AND** changing it back hides the field again

#### Scenario: Saved value decides the initial state

- **WHEN** a settings page loads with a saved controlling value that fails a field's conditions
- **THEN** that field is hidden on arrival

### Requirement: A section is hidden with its heading when its conditions are not met

A section definition SHALL accept a `conditions` key with the same shape, operators and AND logic a field's takes. A conditional section SHALL render its heading and its `form-table` inside one wrapper element carrying the section's slug and its resolved conditions, so that hiding the section takes the heading with it rather than leaving it above an empty table. The wrapper SHALL come from `add_settings_section()`'s `before_section`/`after_section` args, and no `section_class` SHALL be set with them, because core runs `before_section` through `sprintf()` when a class is present and a `%` inside a condition value would be read as a placeholder.

#### Scenario: Section whose conditions fail

- **WHEN** a section declares conditions that the current form state does not satisfy
- **THEN** neither its heading nor any of its fields are on screen

#### Scenario: Section becomes applicable

- **WHEN** the admin chooses a value that satisfies the section's conditions
- **THEN** the heading and the section's fields appear together

### Requirement: The controlling fields reach the browser before the script that reads them

The controlling-field list SHALL be printed ahead of the script that consumes it, because that script reads it as it parses rather than on document ready. Data printed after the script SHALL be treated as a defect: the script reads an empty list, binds no listener, and every conditional field and section keeps the state it loaded with.

#### Scenario: Inline data position

- **WHEN** a page carries any conditional field or section
- **THEN** the controlling fields are added to the admin script in the `before` position

#### Scenario: A page whose only condition is on a section

- **WHEN** no field on the page declares conditions but a section does
- **THEN** the admin script is still enqueued and still receives the controlling fields
