## Why

The `advanced` and `fieldset` container field types render their children with a **hardcoded `switch`** in `init_advanced()` / `init_fieldset()` that only handles `checkbox`, `text`/`email`/`url`/`number`, `textarea`, and `select`. Any other child type — most importantly `repeater`, but also `field_map`, `radio`, `richtext`, `sortable`, or a nested `advanced`/`fieldset` — renders **nothing**. So a container cannot group a repeater with related controls, and consumers are forced into CSS workarounds to visually group sibling top-level fields that the library cannot actually nest.

The library already has a unified per-field renderer — `render_unbound()` → `render_with_value()` — that dispatches **every** field type (including `repeater`, `field_map`, and recursively `advanced`). The containers simply don't use it.

## What Changes

- `init_advanced()` and `init_fieldset()` render each child by delegating to that child's own `render_unbound()` (the same path top-level fields use), instead of the hardcoded type `switch`.
- Containers therefore support **any** child field type, including `repeater`, `field_map`, `radio`, `richtext`, `sortable`, and nested `advanced`/`fieldset`.
- Each child's title continues to render as a heading above its control; the child's own description continues to render (via its renderer).
- No change to how children are registered or saved — children are already registered as their own settings via `child->init(false)`, so delegation only affects **rendering**.
- Backward compatible: existing `advanced`/`fieldset` groups of simple fields render equivalently (checkbox keeps its hidden-input trick, etc.). Markup may differ in minor, benign ways for a few types.

## Capabilities

### New Capabilities

- `field-containers`: how `advanced` and `fieldset` container fields render and nest their child fields.

### Modified Capabilities

<!-- none — no existing specs in this repo yet -->

## Impact

- **Code**: `src/WP_Setting.php` — the child-render loops in `init_advanced()` (~L1628) and `init_fieldset()` (~L1731). Replace the per-type `switch` with a title render + `$child->render_unbound()`.
- **No data/migration impact**: child registration, option storage, and sanitization are unchanged.
- **Consumers**: unlocks native grouping (e.g. a `repeater` + its `advanced` expression in one container) — removes the need for downstream CSS card hacks. No consumer action required unless they want to adopt nesting.
- **Risk surface**: rendering parity for existing simple-field containers (markup + checkbox save behavior) — covered by tests.
