## 1. Storage adapter

- [x] 1.1 Add the `WP_Settings_Table_Storage` interface.
- [x] 1.2 Add `WP_Settings_Table_Option_Storage` with the current option-array behaviour.
- [x] 1.3 Add `WP_Settings_Table_Custom_Table_Storage` with schema install, upsert, per-row read and per-row delete.

## 2. Wire it into the table

- [x] 2.1 Resolve the adapter lazily from the `storage` argument so the text domain is known by the time the option or table name is built.
- [x] 2.2 Rewrite save, delete, toggle, toggle status and bulk to per-row reads and writes.
- [x] 2.3 Give `generate_row_id()` a collision suffix.

## 3. Prove it

- [x] 3.1 Unit tests for both adapters.
- [x] 3.2 A test asserting no handler writes the whole row set.
- [x] 3.3 Sync the delta into `openspec/specs/` and archive the change.
