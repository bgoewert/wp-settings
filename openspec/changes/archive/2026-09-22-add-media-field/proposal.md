## Why

No field type chooses a file from the media library ([#31](https://github.com/bgoewert/wp-settings/issues/31)). A logo, an email masthead or an og:image default becomes a `url` field somebody pastes into, or a `number` holding an attachment id with nothing on screen to say what it points at. Every consumer that wants a real picker writes the same hidden input, preview, Choose and Remove buttons over `wp.media`, and the hand-rolled version fails quietly: `wp.media` enqueued too early, a preview that does not clear on Remove, an id whose attachment was deleted.

## What Changes

- A `media` field type storing an attachment id, with Choose and Remove buttons over the `wp.media` modal and a preview.
- `mime_types` (default `image`) filters the modal and is enforced on save; `size` (default `medium`) names the preview's registered size.
- Saving keeps only an id that still resolves to an attachment of an accepted type; anything else stores `''`.
- `WP_Setting::get_attachment_url($name, $size = 'full')` reads the stored id back as a URL.
- `wp_enqueue_media()` and the field's assets load only on a page with a `media` field.

## Capabilities

### New Capabilities

- `media-field`: the attachment picker field, what it stores, what it accepts, how it previews and how it is read back.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Setting.php` — the type's sanitizer, renderer, kses allow-list entries and the reader.
- `src/WP_Settings.php` — the enqueue gate.
- `src/assets/admin-media.js`, `src/assets/admin-media.css` — new.
