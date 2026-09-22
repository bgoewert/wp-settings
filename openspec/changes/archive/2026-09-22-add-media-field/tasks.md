## 1. Field

- [x] 1.1 Add the `media` type: sanitizer to an accepted attachment id or `''`, `mime_types` and `size` args
- [x] 1.2 Render the hidden input, the preview with the attachment's alt text, and Choose/Remove buttons named after the field
- [x] 1.3 Allow the preview and data attributes through kses, and mark the type unlabelable
- [x] 1.4 Add `WP_Setting::get_attachment_url()`

## 2. Assets

- [x] 2.1 Drive `wp.media` from `admin-media.js`: preselect the current choice, mirror the preview, clear it on Remove
- [x] 2.2 Enqueue `wp_enqueue_media()` and the assets only on a page with a media field

## 3. Tests and documentation

- [x] 3.1 Unit tests: sanitizing, MIME matching, preview, buttons, reader, enqueue gate
- [x] 3.2 Integration tests against real attachments and kses; e2e through the real modal with axe
- [x] 3.3 README: the type, its args and the reader
