## Why

Conditional visibility has been in the library since 2.6.0 and grew a section-level form in 4.6.0 ([#24](https://github.com/bgoewert/wp-settings/issues/24)), but no spec describes it. Two defects shipped in that time were only found by writing browser tests for the new section support, and both had been true of field conditions for as long as the feature existed:

1. A condition names its controlling field the way that field was declared, while a settings page renders every input under the prefixed option slug — so the reference matched nothing and the field it guarded never appeared. Spelling the slug instead fixed the initial state only, because `get_controlling_fields()` then prefixed the reference a second time and bound the listener to a name no input carries.
2. The controlling fields were printed in an inline script *after* `admin.js`, which reads them as it parses rather than on ready — so it read an empty list, bound no listener, and the page kept whatever state it loaded with.

Both are now fixed and released in 4.6.0. This change records the behaviour they violated, so the rules a rewrite has to preserve are written down rather than living only in the tests.

## What Changes

Documentation only — the code shipped in 4.6.0 (`80117ac`, `a93e7c6`, `7ca4317`). This change adds the capability spec covering:

- Where a condition's `field` reference resolves to, from either spelling, and that a table modal's own fields keep matching on the bare name.
- What a field's conditions hide, and that hiding is presentational — a hidden field still submits.
- That a section takes the same `conditions` key, and that its heading hides with its table rather than sitting above an empty one.
- That the controlling-field data has to reach the browser before the script that reads it.

## Capabilities

### New Capabilities

- `conditional-visibility`: how a field or a section is shown or hidden from another field's value.

### Modified Capabilities

<!-- none -->

## Impact

- **Code**: none — this documents `src/WP_Settings.php`, `src/WP_Setting.php` and `src/assets/admin.js` as they already stand.
- **Consumers**: none. The spec is the existing contract, written down.
