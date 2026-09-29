## Why

A field cannot carry a button ([#32](https://github.com/bgoewert/wp-settings/issues/32)). A consumer that generates a credential — the Support Portal's inbound shared secret, Seiler Media's Cloudflare signing key — renders its Generate control under the Save button through a separate `do_action`, far from the input it fills. An encrypted `password` field feels it worst: once stored it renders as a placeholder, and the control that would change it is somewhere else on the tab.

## What Changes

- An `actions` arg on any field: a list of `label`, `action` and optional `capability` (default `manage_options`), rendered as buttons beside a text-like input, or under the field for other types and when `actions_position` is `below`.
- Each button submits its own form to `admin-post.php`, printed in the admin footer and reached through the `form` attribute, carrying the action, the field's slug and a nonce for the action.
- The library verifies the nonce and capability before the consumer's `admin_post_{action}` handler and redirects back to the page after it.

## Capabilities

### New Capabilities

- `field-actions`: buttons a field declares, the requests they send and the checks around the consumer's handler.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Setting.php` — the arg, the buttons, the footer forms, the handler bookends, `form` on the button kses entry.
