## Why

A derived reading has no field type ([#33](https://github.com/bgoewert/wp-settings/issues/33)). A row that reports a connection state or a last-sync time cannot live in the settings table, so it cannot carry an `actions` button either: the Support Portal's Test connection button hangs off the client secret, three fields from the state it tests, and the portal renders both rows itself above the table with hand-rolled `admin-post` forms. `readonly` is not a substitute — it renders a focusable input backed by an option a save writes.

## What Changes

- A `status` field type: `value`, a string or a callable, rendered as text in the value column.
- No option is seeded, registered or written on save.
- `actions` works as on any field, beside the value by default.

## Capabilities

### New Capabilities

- `status-field`: a display-only row reporting a derived value.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Setting.php` — the type's renderer, and skipping the option for it in `init()` and `save()`.
