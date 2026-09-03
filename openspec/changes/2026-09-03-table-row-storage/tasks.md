## 1. Storage adapter

- [ ] 1.1 Add the `WP_Settings_Table_Storage` interface.
- [ ] 1.2 Add `WP_Settings_Table_Option_Storage` with the current option-array behaviour.
- [ ] 1.3 Add `WP_Settings_Table_Custom_Table_Storage` with schema install, upsert, per-row read and per-row delete.

## 2. Wire it into the table

- [ ] 2.1 Resolve the adapter lazily from the `storage` argument so the text domain is known by the time the option or table name is built.
- [ ] 2.2 Rewrite save, delete, toggle, toggle status and bulk to per-row reads and writes.
- [ ] 2.3 Give `generate_row_id()` a collision suffix.

## 3. Prove it

- [ ] 3.1 Unit tests for both adapters.
- [ ] 3.2 A test asserting no handler writes the whole row set.
- [ ] 3.3 Sync the delta into `openspec/specs/` and archive the change.
