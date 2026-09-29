## 1. Status field

- [x] 1.1 Render `value`, resolving a callable at render time, through kses
- [x] 1.2 Skip `add_option()`, `register_setting()` and the save for the type
- [x] 1.3 Render `actions` beside the value unless `actions_position` is `below`

## 2. Tests and documentation

- [x] 2.1 Unit tests: rendering, callable value, no option, no save, actions
- [x] 2.2 E2e: the row renders in the table, an action updates it, a save stores nothing, axe
- [x] 2.3 README: the type and its arguments
