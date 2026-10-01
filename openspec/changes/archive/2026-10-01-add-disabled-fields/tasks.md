## 1. Disabled fields

- [x] 1.1 Wrap the bound render in a disabled fieldset when `disabled` is set
- [x] 1.2 Answer the registered sanitizer with the stored value, and skip `save()`
- [x] 1.3 Ignore the arg on `advanced` and `fieldset`

## 2. Tests and documentation

- [x] 2.1 Unit tests: wrapper per type, sanitizer, save, encrypted, containers
- [x] 2.2 Integration: a `null` write through real `update_option()` keeps the value
- [x] 2.3 E2e: the checkbox renders checked and disabled, a save keeps it, axe
- [x] 2.4 README: the arg, replacing the note that `disabled` is unsupported
