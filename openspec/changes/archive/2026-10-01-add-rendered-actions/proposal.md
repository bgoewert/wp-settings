## Why

A `status` row's actions can only be `admin-post` forms ([#35](https://github.com/bgoewert/wp-settings/issues/35)). The Support Portal's Preview, Run now, Preview discovery and Run discovery controls answer in place over Ajax with a rendered table or a tally, so as `actions` entries each click would cost a page load and a transient to carry the answer across the redirect. Rendering them through `value` does not work either: `data-*` is stripped from a `<button>` by the field's kses list, and the consumer's script needs it to bind the control.

## What Changes

- An `actions` entry may declare `render`, a callable given the field that returns the control's markup, printed in declared order among the other buttons.
- A rendered entry gets no form, nonce or hook, and honors `capability`.
- `<button>` keeps `data-*` attributes through the field's kses list.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities

- `field-actions`: an entry can render its own control.

## Impact

- `src/WP_Setting.php` — `render_actions()`, and `data-*` on `button` in `$allowed_html`.
