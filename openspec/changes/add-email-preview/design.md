## Context

See proposal.md — Why. wp-mu-email wraps core's system mail at priority 20 on `wp_new_user_notification_email` and its siblings: `Seiler_Mail_Brand::filter_email()` replaces `message` with branded HTML and adds a `Content-Type: text/html` header. Its `body_html()` escapes the plain text, splits it on blank lines into `<p style="…">`, turns single newlines into `<br />`, and links bare URLs with a themed `<a style="…">`. Its Colors tab repaints a preview by swapping placeholder colors client-side, which works only because colors change token values, not markup.

A field's input id is its slug (`label_for` defaults to it), so a slug is also an element id.

## Goals / Non-Goals

**Goals:**

- The preview is what the recipient gets: one code path renders and sends.
- Live repaint follows whatever template the site installed, with no request per keystroke.

**Non-Goals:**

- Branding. wp-settings ships no template.
- A "send test" button. A field's `actions` can call `send()`.
- Storing the templates. The consuming plugin owns its subject and body fields and constructs the email from their values.
- HTML bodies. The body is plain text, as core's system mail is.

## Decisions

**The filter takes core's array shape.** `apply_filters('wp_settings_email', ['to', 'subject', 'message', 'headers'], $email, $context)`. A wrapper already written for `wp_new_user_notification_email` attaches unchanged. A per-email filter (`wp_settings_email_{id}`) was considered and left out: a site wrapper wants all of them, and the email object as an argument lets a filter pick one.

**Placeholders are substituted before the filter, as plain text.** The wrapper escapes the body, as it does core's; escaping here would double-escape.

**HTML is detected from the filtered headers, not the message.** A `Content-Type: text/html` header (string or array headers) means the wrapper produced HTML. Sniffing for `<` would misread a plain-text body that mentions a tag.

**`watch` is keyed: `['subject' => id, 'body' => id]`.** The issue sketched a list; a list cannot say which input is the subject. Values are element ids, which for settings fields are their slugs, so the same argument works in a metabox.

**Live repaint from a sentinel render, parsed in PHP.** The page renders the email a second time with a sentinel subject and the body `A1\nA2\n\nB https://wps-sentinel.invalid/u`, sentinels alphanumeric so escaping leaves them intact. PHP finds them in the filtered result and derives the template parts:

- line break: the markup between `A1` and `A2`;
- paragraph open: the longest common suffix of the text before `A1` and the text between `A2` and `B`;
- paragraph close: the longest common prefix of the text between `A2` and `B` and the text after the link;
- separator, document prefix and suffix: what is left on either side;
- link: the span from the `<` before the URL's first occurrence to the `>` after its last, with each occurrence a slot; a bare URL is a slot alone;
- subject: the text before and after the subject sentinel.

Any sentinel missing or found more than once yields no template, and the preview shows the "updates when saved" note. Deriving in PHP keeps the brittle part under unit tests; the script only assembles. The alternative, a debounced admin-ajax render, was the open question on #44 and was dropped for a request per pause in typing.

**The script mirrors `body_html()`'s text rules.** Normalize line endings, escape `& < > " '` as `htmlspecialchars(ENT_QUOTES)` does, split on two or more newlines, trim and drop empty paragraphs, link `\bhttps?://[^\s<]+` with trailing `.,!?)` moved outside the link. Sample values are substituted into the typed text before escaping, matching the server.

**The preview loads its own script.** `preview_markup()` enqueues `admin-email-preview.js` and prints its data in a `<script type="application/json">` beside the frame, `JSON_HEX_TAG` encoded, so several previews on one screen and a metabox work without the settings page knowing. No jQuery.

**`srcdoc` is escaped with `htmlspecialchars(ENT_QUOTES)`, not `esc_attr()`.** `esc_attr()` leaves existing entities alone, so `&quot;` around a font name would decode to a bare quote and end a style attribute. The frame is `sandbox=""`.

## Risks / Trade-offs

- [A wrapper that changes markup by content, or links differently than `body_html()`] → the repaint drifts until the next page load. `render()` and `send()` stay the source of truth, and the e2e suite runs a `body_html()`-shaped wrapper.
- [A wrapper that leaves the body unescaped] → typed markup shows literally in the repaint but renders in the sent mail. That wrapper is unsafe for core's mail too.
- [The sentinel render runs the filter a second time per page load] → one extra string transform; no mail is sent.

## Migration Plan

Additive. wp-mu-email adding `wp_settings_email` to its wrapped filters is a separate change in that repo.
