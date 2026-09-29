## Context

See proposal.md — Why.

## Goals / Non-Goals

**Goals:**

- A status row sits in the settings table, aligned with the fields, and takes `actions`.
- Nothing about it is stored.

**Non-Goals:**

- Refreshing the value without a page load. The actions already redirect back, which re-renders it.

## Decisions

**`value` accepts a callable, called at render.** A connection state is computed from other options or a remote check; building it in the constructor would run it on every `admin_init`, including `admin-post.php` requests where the row is never shown.

**The value passes through the field's kses list, not `esc_html()`.** A status line often wants emphasis or a link to a log, and the description already takes the same markup.

**No `label_for`.** There is no control for the row heading to name; `status` joins `UNLABELABLE_TYPES`.

**Actions sit beside the value.** A status is one line, like a text input, so the button reads as acting on it; `actions_position => 'below'` still applies.
