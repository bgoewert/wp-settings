## ADDED Requirements

### Requirement: An action entry can render its own control

An `actions` entry declaring a callable `render` SHALL be rendered by printing the callable's return value, given the field, in declared order among the field's other action buttons, through the field's kses list. It SHALL be rendered only for a user holding its `capability`, defaulting to `manage_options`, and SHALL NOT receive a form, a nonce or an `admin_post_{action}` hook. A `<button>` SHALL keep its `data-*` attributes through the field's kses list.

#### Scenario: A status row declares a Preview control

- **WHEN** a field declares a `render` entry returning a `<button type="button" data-run="preview">`
- **THEN** the button renders with its `data-run` attribute beside the field's other actions, and no form is printed for it

#### Scenario: The admin presses a rendered control

- **WHEN** the consumer's script handles a click on the rendered control
- **THEN** the page does not navigate
