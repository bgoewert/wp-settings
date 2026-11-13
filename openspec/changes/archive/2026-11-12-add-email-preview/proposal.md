## Why

A plugin built on wp-settings that sends its own mail cannot show an admin what that mail will look like ([#44](https://github.com/bgoewert/wp-settings/issues/44)). wp-mu-email hand-builds a preview for its Colors tab, and seiler-events needs one for its registration notification templates and its "email all attendees" metabox (WEB-578), which would be the third copy.

## What Changes

- A `WP_Settings_Email` object: an id, a subject and a plain-text body with `{placeholder}` tokens, and sample values. `send()` mails it; `render()` returns what `send()` would mail.
- Both pass the message through a `wp_settings_email` filter shaped like core's `wp_new_user_notification_email`, so a site that wraps core's system mail can wrap these the same way. wp-settings brands nothing.
- An `email_preview` field type: the rendered sample in a sandboxed iframe, or a `<pre>` when the message is plain text, with the subject above it. It stores nothing.
- `watch` names the subject and body inputs; typing in them repaints the preview in the browser, with no request, still in the site's template.
- `WP_Settings_Email::preview_markup()` renders the same preview outside a settings page, such as in a post metabox.

## Capabilities

### New Capabilities

- `email-composition`: composing and sending a templated email through the site's mail filter.
- `email-preview`: showing an email as it will be sent, and repainting it as its template is edited.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Settings_Email.php` — new.
- `src/WP_Setting.php` — the `email_preview` renderer, and skipping its option as `status` does.
- `src/assets/admin-email-preview.js` — new.
- wp-mu-email would add `wp_settings_email` to the core filters it already wraps. That is its own change, outside this repo.
