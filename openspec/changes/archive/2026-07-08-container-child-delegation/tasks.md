## 1. Delegate child rendering

- [x] 1.1 In `init_advanced()` (`src/WP_Setting.php` ~L1608), replace the per-child `switch ($child->type)` with: render the child's title as its heading, then delegate to the child's own field callback (`init_<type>` → `render_unbound()`).
- [x] 1.2 Do the same in `init_fieldset()` (~L1719), preserving its `<fieldset>`/`<legend>` shell and per-child `<tr>`/table layout.
- [x] 1.3 Confirm children are `init()`-ed before render (they are, via `$child->init(false)` in `init()`); the child callback reads each child's stored value (incl. repeater JSON decode).
- [x] 1.4 Remove the now-dead inline per-type markup from both container methods (and the now-unused `get_enter_submit_attribute()` helper).

## 2. Backward compatibility

- [x] 2.1 Verify a `checkbox` child still emits its hidden `value="0"` companion input (unchecked boxes save). (test: `test_advanced_unchecked_checkbox_child_submits_zero`, existing `test_fieldset_saves_checkbox_child`)
- [x] 2.2 Verify `text`/`textarea`/`select` children render and save unchanged; note/accept minor markup differences. (existing fieldset save tests + `test_init_advanced_text_child_renders_input`)
- [x] 2.3 Verify child titles and descriptions still appear. (asserted in new container tests)

## 3. New nesting support

- [x] 3.1 Verify a `repeater` child renders fully inside an `advanced` and a `fieldset` (rows, add/remove, description) and round-trips on save. (`test_advanced_renders_repeater_child`, `test_fieldset_renders_repeater_child`, `test_advanced_repeater_child_round_trips_saved_value`)
- [x] 3.2 Verify a `field_map` child renders and saves inside a container. (`test_advanced_renders_field_map_child`)
- [x] 3.3 Verify `radio` and `richtext` children render (previously produced no output). (`test_advanced_renders_radio_child`, `test_advanced_renders_richtext_child`)
- [x] 3.4 Verify a nested `advanced`/`fieldset` child renders its own children. (`test_advanced_renders_nested_advanced_child`)

## 4. Tests

- [x] 4.1 Add unit tests: container with each child type renders non-empty output for repeater/field_map/radio/richtext/nested-advanced.
- [x] 4.2 Add a test asserting the unchecked-checkbox child submits `0`.
- [x] 4.3 Add a test asserting a nested repeater's saved value renders back into the container.
- [x] 4.4 Run the existing suite; confirm no regressions in current `advanced`/`fieldset` behavior. (135 tests pass)

## 4b. Custom-callback child fix (post-archive, found by downstream smoke test)

- [x] 4b.1 Invoke child callbacks with `$child->args` in both `init_advanced()` and `init_fieldset()` (`call_user_func($child->callback, $child->args)`), mirroring WP's field-callback contract. Fixes `ArgumentCountError` WSOD for children whose custom callback requires `$args`.
- [x] 4b.2 Add tests: a child with a custom callback that requires `$args` renders inside an `advanced` and a `fieldset` without error (`test_advanced_child_custom_callback_receives_args`, `test_fieldset_child_custom_callback_receives_args`).

## 5. Release

- [ ] 5.1 Update CHANGELOG per Common Changelog (feat: containers render any child type) at release time.
- [ ] 5.2 Cut a minor release; downstream (seiler-products) bumps the dependency and can then nest Sync Filters + Advanced Filter Logic and drop its CSS card hack.
