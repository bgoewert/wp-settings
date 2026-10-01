# field-actions Specification

## Purpose

Lets a field carry the buttons that act on it — Generate, Rotate — so a consumer declares the control beside the input it fills and writes only the work in its `admin_post_{action}` handler, while the library owns the form, the nonce, the capability check and the redirect.

## Requirements

### Requirement: A field renders the actions it declares

A field SHALL accept an `actions` argument listing entries with a `label`, an `action` and an optional `capability` defaulting to `manage_options`, and SHALL render one button per entry for a user holding that entry's capability. The field SHALL accept an `actions_position` argument: on a single-line text-like input the buttons SHALL render beside the input unless it is `below`, and on every other type they SHALL render below the field. An entry without a label, or whose action is not made of letters, digits, `_` and `-`, SHALL be ignored. Each button's accessible name SHALL be its label followed by the field title.

#### Scenario: A field declares two actions

- **WHEN** a field declares Generate and Rotate
- **THEN** both buttons render under the field, named "Generate {title}" and "Rotate {title}"

#### Scenario: A password field declares an action

- **WHEN** a password field declares Generate without `actions_position`
- **THEN** the button renders beside the input, after the Show button

#### Scenario: A field asks for its actions below

- **WHEN** a text-like field sets `actions_position` to `below`
- **THEN** the buttons render in a paragraph under the description

#### Scenario: The user lacks the capability

- **WHEN** the current user does not hold an action's capability
- **THEN** that action's button and form are not rendered

### Requirement: An action posts its own form

Each rendered button SHALL submit a form posting to `admin-post.php` with the action, the field's slug as `setting`, and a nonce for the action, and SHALL NOT submit the settings form the field sits in.

#### Scenario: An admin presses an action

- **WHEN** an admin presses a field's action button
- **THEN** the request runs `admin_post_{action}` and carries only the action, the slug and the nonce

#### Scenario: An admin presses Enter in the field

- **WHEN** an admin presses Enter in a field that declares an action
- **THEN** the settings form saves and the action does not run

### Requirement: The library checks and returns around the consumer's handler

For each declared action the library SHALL verify the nonce and the capability before the consumer's `admin_post_{action}` handler runs, refusing the request otherwise, and SHALL redirect to the referring page after every handler has run.

#### Scenario: A request arrives without the nonce

- **WHEN** a request to `admin_post_{action}` carries no valid nonce for the action
- **THEN** it is refused and the consumer's handler does not run

#### Scenario: The handler returns

- **WHEN** the consumer's handler returns without redirecting
- **THEN** the admin is sent back to the page the action was pressed on

### Requirement: An action entry can render its own control

An `actions` entry declaring a callable `render` SHALL be rendered by printing the callable's return value, given the field, in declared order among the field's other action buttons, through the field's kses list. It SHALL be rendered only for a user holding its `capability`, defaulting to `manage_options`, and SHALL NOT receive a form, a nonce or an `admin_post_{action}` hook. A `<button>` SHALL keep its `data-*` attributes through the field's kses list.

#### Scenario: A status row declares a Preview control

- **WHEN** a field declares a `render` entry returning a `<button type="button" data-run="preview">`
- **THEN** the button renders with its `data-run` attribute beside the field's other actions, and no form is printed for it

#### Scenario: The admin presses a rendered control

- **WHEN** the consumer's script handles a click on the rendered control
- **THEN** the page does not navigate
