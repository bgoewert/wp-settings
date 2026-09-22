## ADDED Requirements

### Requirement: A media field stores an attachment id
The library SHALL provide a `media` field type whose value is the id of an attachment chosen from the media library, or an empty string when none is chosen. On save it SHALL keep an id only when it resolves to an attachment whose MIME type the field accepts, and SHALL store an empty string otherwise.

#### Scenario: An admin chooses an image
- **WHEN** an admin chooses an image in the media modal and saves
- **THEN** the setting stores that attachment's id

#### Scenario: The submitted id is not an accepted attachment
- **WHEN** the submitted value is not a positive integer, is not an attachment, or is an attachment of a type the field does not accept
- **THEN** the setting stores an empty string

#### Scenario: An admin removes the choice
- **WHEN** an admin presses Remove and saves
- **THEN** the setting stores an empty string

### Requirement: The accepted types and the preview size are configurable
The field SHALL accept a `mime_types` argument naming a MIME family, an exact MIME type, or a list of either, defaulting to images, and an empty list SHALL accept any attachment. The accepted types SHALL filter the media modal's library. The field SHALL accept a `size` argument naming the registered image size the preview renders, defaulting to `medium`.

#### Scenario: A family is named
- **WHEN** the field accepts `image` and an attachment is `image/png`
- **THEN** the attachment is accepted

#### Scenario: An exact type is named
- **WHEN** the field accepts `image/jpeg` and an attachment is `image/png`
- **THEN** the attachment is rejected

### Requirement: The field previews what it holds
The field SHALL preview a chosen image at the configured size with the attachment's own alt text, falling back to the attachment's title, and SHALL preview a non-image by its file name. A stored id that no longer resolves SHALL render as no choice. The Remove button SHALL be hidden when nothing is chosen, and focus SHALL move to the Choose button when Remove is pressed.

#### Scenario: The attachment was deleted after saving
- **WHEN** the stored id's attachment has been deleted
- **THEN** the field renders with no preview and an empty value, and the next save clears the setting

#### Scenario: Two media fields are on one page
- **WHEN** a page carries two media fields
- **THEN** each field's Choose and Remove buttons carry that field's title in their accessible names

### Requirement: The stored id can be read as a URL
The library SHALL provide `WP_Setting::get_attachment_url()` taking the setting name and a size, defaulting to `full`. It SHALL return the image URL at that size for an image, the file URL for a non-image, and an empty string when nothing is chosen or the attachment no longer exists.

#### Scenario: A consumer reads a chosen logo
- **WHEN** the setting holds an image's id
- **THEN** the reader returns that image's URL at the requested size

#### Scenario: Nothing resolves
- **WHEN** the setting is empty or its attachment was deleted
- **THEN** the reader returns an empty string

### Requirement: The media modal loads only where it is used
The library SHALL call `wp_enqueue_media()` and enqueue the field's script and stylesheet on its settings page only when a media field is registered on it, including one inside an advanced container.

#### Scenario: The page has no media field
- **WHEN** the settings page carries no media field
- **THEN** the media modal and the field's assets are not enqueued
