## Context

See proposal.md — Why.

## Goals / Non-Goals

**Goals:**

- Every option-backed type renders inert, checkboxes included.
- Nothing a POST carries can change the value while the field is disabled.

**Non-Goals:**

- Table columns and containers. A column is stored by the table, not an option, and a container stores nothing itself.

## Decisions

**A disabled fieldset around the control, not `disabled` per renderer.** A fieldset disables every descendant control, so one wrapper covers a checkbox's hidden `0`, a select, a repeater's buttons and the field's actions, without touching a dozen renderers. Its border and padding are reset inline, since the admin stylesheet only loads on pages with tables or conditions.

**The sanitizer returns the stored value; no hidden input.** The issue proposed a hidden copy of the value to round-trip it. The sanitizer guard is needed anyway, because `sanitize` must not be able to change the value, and with it the copy is redundant: options.php writes `null`, the guard answers with the stored value, and `update_option()` sees no change. The guard also refuses a forged POST, which a hidden input would carry straight through.

**Raw stored value, outside the encrypting sanitizer.** An encrypted field keeps its ciphertext as stored, rather than being decrypted and ciphered again.

## Risks / Trade-offs

- **Script-driven types** (`richtext`, `sortable`, `dual_list`) can still react to input a disabled fieldset does not block → the value is kept regardless; the README says so.
