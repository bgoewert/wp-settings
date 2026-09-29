## Context

See proposal.md — Why.

## Goals / Non-Goals

**Goals:**

- A consumer declares the button and writes only the work in `admin_post_{action}`.
- More than one action per field, since Generate and Rotate are different buttons.

**Non-Goals:**

- AJAX. A full-page post keeps the no-JS path and the redirect is what the consumer's own notices already expect.
- Reporting the outcome. The consumer redirects with its own query arg when it has something to say.

## Decisions

**An array per action, not a callback.** A callback runs inside the render pass, so it cannot own the nonce, the capability check or the redirect, and the consumer writes an `admin-post` handler anyway. The array keeps the split `sanitize_callback` already has: the library owns the plumbing, the consumer owns the work.

**Footer forms and the `form` attribute.** The field renders inside the settings form and forms cannot nest. `formaction` on a button in the settings form would post every setting along with it, and its `action=update` would win over the action in the URL. A form printed on `admin_footer` and a button naming it posts only the action, needs no script, and — because the button's form owner is not the settings form — cannot become the settings form's default button, so Enter in the field still saves.

**Bookends on the consumer's hook.** Priority 0 runs `check_admin_referer()` and the capability check; `PHP_INT_MAX` redirects to the referer. A handler that redirects and exits first keeps its own destination. The hooks are added in `init()`, which the settings class runs on `admin_init`, so they exist on the `admin-post.php` request where the field is never rendered.

**Inline by default where it can be.** On a single-line input the button beside it — after a password's Show — is where an admin looks for it. A textarea, a list or a picker has no line to share, so those always render below, and `actions_position => 'below'` lets a text field opt into the same.

**The button is hidden without the capability.** A button that can only fail is noise; the server check is the control.

## Risks / Trade-offs

- **Two fields sharing one action with different capabilities** → both checks run, so the stricter one wins.
- **`setting` in the request is not covered by the nonce** → it is a convenience for a shared handler, not an authorization input; the README says so by describing it as the slug only.
