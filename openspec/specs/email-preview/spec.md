# email-preview Specification

## Purpose
Shows an admin an email as it will be sent, in a settings field or a metabox, and repaints it in the browser as its subject and body are typed, following the site's mail template.

## Requirements

### Requirement: A preview shows an email as it will be sent

An email preview SHALL show the email rendered with its sample values: the subject as text, then the message. When the rendered headers declare `Content-Type: text/html`, the message SHALL be shown in an iframe sandboxed with no permissions, whose document is the message exactly, entities included. Otherwise the message SHALL be shown as preformatted text. The iframe SHALL carry a title naming the email.

#### Scenario: A site wraps the email in HTML

- **WHEN** a filter wraps the email in an HTML template
- **THEN** the preview's iframe document equals the rendered message, and no script in it runs

#### Scenario: No site wrapper

- **WHEN** no filter changes the email
- **THEN** the preview shows the plain-text body with its line breaks, and no iframe

### Requirement: An email_preview field stores nothing

An `email_preview` field SHALL render a preview of its `email` in the settings row, SHALL NOT seed, register or write an option, and its row heading SHALL NOT be a `<label>`. When `email` is callable it SHALL be called when the row renders. A field's `watch` SHALL name the subject and body fields by their unprefixed names.

#### Scenario: The settings are saved

- **WHEN** the tab holding an email_preview field is saved
- **THEN** no option exists under the field's slug, and the page shown after the save previews the saved subject and body

### Requirement: A preview follows its template as it is edited

A preview's `watch` argument MAY name a subject input and a body input. Typing in either SHALL repaint the preview in the browser without a request to the server, with the sample values substituted into the typed text. For a site wrapper that wraps each paragraph and each link the same way regardless of their content, the repainted message SHALL equal what rendering the typed subject and body would produce.

#### Scenario: A body is edited under a site wrapper

- **WHEN** an admin types a body of two paragraphs, one containing a URL, into a watched body input
- **THEN** the iframe shows both paragraphs and the link in the site template's markup, without a page load or a request

#### Scenario: A body is edited with no wrapper

- **WHEN** an admin types into a watched body input on a site with no wrapper
- **THEN** the preformatted preview shows the typed text with sample values substituted

#### Scenario: The wrapper hides the edit points

- **WHEN** the site wrapper's output does not contain the subject or body where the preview can find them
- **THEN** the preview stays as first rendered and says it updates when the settings are saved

### Requirement: A preview renders outside a settings page

The same preview SHALL be available as markup for a screen that is not a settings page, such as a post metabox, with `watch` naming element ids, and SHALL load its script there.

#### Scenario: A metabox preview

- **WHEN** a metabox prints an email's preview watching its own subject and body inputs
- **THEN** the preview renders and repaints as it does in a settings field
