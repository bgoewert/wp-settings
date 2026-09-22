## Context

See proposal.md — Why. The Customizer has had this control since 3.9, and core stores `custom_logo` as an attachment id for the reason this field does.

## Goals / Non-Goals

**Goals:**
- One declared field replaces the forty lines of PHP and twenty of JS every consumer writes.
- A stored value never renders as a broken image.

**Non-Goals:**
- Multiple attachments. A gallery is a list with an order, and a different field.
- Cropping. The Customizer's cropped-image control is a second modal state; a registered size covers the preview.

## Decisions

**Store the id, not the URL.** A URL breaks on a domain change or a CDN move; an id does not, and the size is a read-time choice. The reader exists so a consumer does not have to remember the value is an id.

**`''` for no choice, not `0`.** The conditional-visibility `empty` operator tests the submitted string, and `''` is what every other cleared field stores.

**Validate on save and on render.** The sanitizer rejects an id that is not an attachment or not an accepted type, so a crafted POST cannot point the setting at an arbitrary post. Render runs the same check, so an attachment deleted after saving shows as no choice and the next save clears it.

**The row heading does not label the field.** The value lives in a hidden input, which is not labelable, and pointing the heading at the Choose button would replace the button's own name. The type joins `UNLABELABLE_TYPES`, and each button's name is its visible word plus the field title — "Choose Site Logo" — the same way the dual list names its move buttons.

**Alt text from the attachment.** The field title says what the slot is for, not what fills it. An empty alt falls back to the attachment title, so the preview is never nameless.

**Build the modal on first click.** `wp_enqueue_media()` is gated on a media field being registered, the way the other assets are gated; the frame itself is created when Choose is pressed, not on load.

## Risks / Trade-offs

- **A consumer's `sanitize_callback` replaces the attachment check** → the same as every other type; the README states the default.
- **The stored type is `int|string`** → `''` for none is what the rest of the library stores for an empty field, and the reader hides the distinction.
