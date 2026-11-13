# email-composition Specification

## Purpose
Lets a plugin compose an email from a subject and plain-text body template, and send or preview it through the site's `wp_settings_email` filter, so a site that wraps core's system mail wraps these too.

## Requirements

### Requirement: An email fills its placeholders from a context

An email SHALL replace each `{name}` token in its subject and body with the context's value for `name`, as plain text. A token the context has no value for SHALL be left as written.

#### Scenario: Every token has a value

- **WHEN** an email with subject `{event_title}: an update` renders with `event_title` set to "Fall Expo"
- **THEN** the subject is "Fall Expo: an update"

#### Scenario: A token has no value

- **WHEN** an email's body contains `{attendee_name}` and the context does not set it
- **THEN** the body still reads `{attendee_name}`

### Requirement: An email passes through the site's mail filter

Before it is sent or previewed, an email SHALL pass its recipient, subject, message and headers through the `wp_settings_email` filter as an array with the keys `to`, `subject`, `message` and `headers`, the same shape core's `wp_new_user_notification_email` filter takes, with the email and its context as further arguments. When a filter returns anything other than an array with a string `message`, the unfiltered email SHALL be used.

#### Scenario: A site wraps the message

- **WHEN** a filter on `wp_settings_email` replaces the message with an HTML template and adds a `Content-Type: text/html` header
- **THEN** the email is sent and previewed with that template and header

#### Scenario: A filter returns something unusable

- **WHEN** a filter on `wp_settings_email` returns `null`
- **THEN** the email is sent with its unfiltered subject and body

### Requirement: Sending an email mails what it renders

Sending an email SHALL call `wp_mail()` with the filtered recipient, subject, message and headers, and report whether `wp_mail()` accepted it. Rendering an email with the same context SHALL return the same subject, message and headers without sending anything.

#### Scenario: A send and a render of the same context

- **WHEN** an email is rendered and then sent with the same context
- **THEN** the message `wp_mail()` receives is identical to the rendered message

#### Scenario: wp_mail fails

- **WHEN** `wp_mail()` returns false
- **THEN** sending the email returns false

### Requirement: An email declares an id

An email SHALL require a non-empty `id`, and SHALL reject construction without one.

#### Scenario: No id

- **WHEN** an email is constructed without an `id`
- **THEN** construction throws an `InvalidArgumentException`
