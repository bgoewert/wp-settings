## Context

`WP_Setting` renders a top-level field through its type callback, and every simple callback (`init_type`, `init_checkbox`, `init_select`, `init_textarea`, `init_radio`, `init_hidden`, `init_richtext`) funnels into a single unified renderer:

```
init_<type>() → render_unbound($value, $name, $id) → render_with_value($name, $id, $value)
```

`render_with_value()` (`src/WP_Setting.php` ~L979) already switches over **all** field types — `textarea`, `checkbox`, `select`, `radio`, `sortable`, `table`, `hidden`, `advanced` (recurses `init_advanced`), `field_map`, `repeater`, `richtext`, and default text — each emitting its control plus its own `description`.

The container callbacks do **not** use this. `init_advanced()` (~L1608) and `init_fieldset()` (~L1719) each contain a private `switch ($child->type)` that only handles `checkbox`, `text`/`email`/`url`/`number`, `textarea`, and `select`, re-implementing markup inline. Any other child type falls through and renders nothing. Children are already registered/sanitized independently: `init()` (~L584) calls `$child->init(false)` for each container child, which registers the option and sanitize callback but not an `add_settings_field` row.

## Goals / Non-Goals

**Goals:**

- Containers render any child type by reusing the existing per-field renderer.
- Preserve child titles/descriptions and existing save behavior (esp. checkbox).
- No change to registration, storage, or sanitization.

**Non-Goals:**

- Redesigning the container markup/`<details>`/`<fieldset>` shell or its styling.
- Changing top-level field rendering.
- Adding new field types or a new "group" type (the existing containers become sufficient).
- Conditional-visibility changes (nested `has_conditions()` wrapping already flows through `render_unbound`).

## Decisions

### Decision: Delegate child rendering to `render_unbound()`

**Choice:** In `init_advanced()` and `init_fieldset()`, replace the per-type `switch` with, per child: render the child's title as its heading, then call `$child->render_unbound()` (which routes through `render_with_value()` and handles every type, including `repeater`/`field_map`/nested `advanced`). The child's own renderer emits its description.

**Why:** The unified renderer already exists and is the exact code top-level fields use, so nested and top-level rendering converge on one path — any current or future type "just works" as a child, and there is no second copy of per-type markup to keep in sync. This is the root-cause fix for "containers can't nest a repeater," not a special case for repeater.

**Alternatives considered:**
- *Add a `repeater` case (only) to the container `switch`.* Rejected — fixes one type, leaves `field_map`/`radio`/nested containers broken, and keeps duplicated markup.
- *Introduce a new `group` field type.* Rejected — redundant once `advanced`/`fieldset` can nest anything; more surface, migration churn.

### Decision: Keep title rendering in the container, description in the child

**Choice:** The container prints the child title (heading), then delegates; the child renderer prints the description (as it does at top level).

**Why:** `render_with_value` already appends `description` per type; titles are not part of a field's own control render (top level, WP's `add_settings_field` supplies the `<th>` title). So the container supplies the heading and lets the child supply control + description — no duplication, consistent with top-level output.

### Decision: Invoke the child's callback with `$child->args`

**Choice:** Delegate via `call_user_func($child->callback, $child->args)` (not `render_unbound()` directly, and not a no-arg call). The built-in type callbacks (`init_checkbox()` etc.) declare no parameters and safely ignore the extra argument; the raw stored value (including repeater JSON) is decoded inside those callbacks, which is why the callback — not `render_unbound()` — is the delegation target.

**Why:** WordPress's Settings API passes the field's `$args` to its render callback (`add_settings_field(…, $callback, …, $args)` → `$callback($args)`). A **custom** callback (a consumer passing their own render function) can therefore declare a required `$args` parameter. Invoking it with no args throws `ArgumentCountError` (fatal / WSOD) for the container's children even though the identical field renders fine at top level. Passing `$child->args` makes nested invocation obey the same contract as top-level, so custom-callback children behave identically inside and outside a container.

**Discovered by:** a downstream `seiler-products` smoke test — a custom `callback_update_subscription_prices($args)` child WSOD'd after delegation shipped, because the container invoked callbacks with no args.

## Risks / Trade-offs

- **Markup drift for existing simple children** → the delegated renderers differ slightly from the old inline markup (e.g. `render_checkbox_value` renders the description as an inline `<label>` beside the box rather than a `<p>` below). Mitigation: this is cosmetic and arguably more correct; capture current vs new output in tests and eyeball a container of each simple type before release.
- **Checkbox unchecked-save regression** → the delegated `render_checkbox_value` includes the same hidden `value="0"` companion input, so unchecked boxes still submit. Mitigation: explicit test.
- **Double-registration / value source** → children read their value via `self::get()` inside `render_with_value`; the old inline code did the same. No new state. Mitigation: verify a nested repeater's saved value renders back.
- **Nested-`advanced` recursion** → `render_with_value`'s `advanced` case calls `init_advanced()`, so nesting recurses naturally; guard only by not creating cyclic child graphs (author error, not a library concern).
- **Custom-callback children require `$args`** → WP passes `$args` to a field's render callback; a nested invocation that omits it throws `ArgumentCountError` for any child with a custom callback declaring a required `$args`. Mitigation: containers invoke `call_user_func($child->callback, $child->args)`; built-in callbacks ignore the extra arg. Explicit test with a custom `$args`-requiring callback in both `advanced` and `fieldset`.

## Migration Plan

No consumer migration required — existing containers keep working. Ship as a minor release (new capability, backward compatible). Consumers wanting nesting simply add complex children to an existing `advanced`/`fieldset`. Rollback = revert the two loops; storage untouched.

## Open Questions

- Should the container heading level/markup for a child match top-level `<th>` styling more closely, or keep the current `<p><strong>` heading? Default: keep current heading markup to minimize visual churn; revisit if consumers want form-table parity.
