# Tasks

## 1. Email object

- [x] 1.1 `WP_Settings_Email`: constructor requiring `id`, `{name}` substitution leaving unknown tokens, verified by unit tests for both
- [x] 1.2 `render()` and `send()` through the `wp_settings_email` filter with the unfiltered fallback, verified by unit tests: a wrapping filter, a filter returning `null`, `wp_mail()` returning false, and a render matching what `wp_mail()` received
- [x] 1.3 README: the email object, the filter and its arguments, checked by markdownlint

## 2. Preview markup

- [x] 2.1 `preview_markup()`: subject, then a `sandbox=""` titled iframe with `htmlspecialchars()`-escaped `srcdoc` when the headers declare HTML, or a `<pre>` otherwise, verified by unit tests decoding `srcdoc` back to the rendered message exactly
- [x] 2.2 Sentinel render and template derivation (line break, paragraph open/close, separator, prefix/suffix, link, subject), with `null` when a sentinel is missing or repeated, verified by unit tests against a `body_html()`-shaped wrapper, a no-op filter, and a wrapper that drops the body
- [x] 2.3 Print the template data as `JSON_HEX_TAG` JSON beside the frame and enqueue the script, verified by a unit test that the data is present and the script enqueued

## 3. Live repaint

- [x] 3.1 `admin-email-preview.js`: on input in a watched id, substitute samples, escape, split, link and assemble into `srcdoc` (or the `<pre>`'s text), and set the subject; show the "updates when saved" note when there is no template, verified in 3.3
- [x] 3.2 Harness: a tab with subject and body fields, an `email_preview` watching them, and a `body_html()`-shaped `wp_settings_email` filter toggled by a harness option
- [x] 3.3 E2e: typing two paragraphs and a URL repaints the iframe with no request and matches a server render of the same text; plain text repaints the `<pre>`; a body-dropping wrapper shows the note; axe passes

## 4. email_preview field

- [x] 4.1 Field type rendering `preview_markup()` for its `email` and `watch`, in `UNLABELABLE_TYPES`, skipping option registration and save as `status` does, verified by unit tests that no option exists after a save
- [x] 4.2 README: the field type and its `email` and `watch` arguments, and `preview_markup()` for a metabox, checked by markdownlint

## Workflow follow-up

- Sync `email-composition` and `email-preview` into `openspec/specs/`, replace the archive's `TBD` Purpose stubs, and archive in the `feat:` commit.
- wp-mu-email: add `wp_settings_email` to its wrapped filters (separate repo, needs approval).
