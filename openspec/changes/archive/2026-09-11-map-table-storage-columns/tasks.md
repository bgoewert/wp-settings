## 1. Map the columns

- [x] 1.1 Take the column map, the per-column overrides, `schema` and `install` as an optional constructor argument.
- [x] 1.2 Build every statement — select list, upsert, delete, order — from the configured columns.
- [x] 1.3 Route unmapped keys through the JSON column, and non-scalars back into it.
- [x] 1.4 Generate the schema from the map, or install the supplied body, or install nothing.

## 2. Prove it

- [x] 2.1 Generalize the `$wpdb` stand-in so it parses the column list instead of assuming it.
- [x] 2.2 Unit tests for mapped columns, a supplied schema, a consumer-owned table, renamed and dropped columns.
- [x] 2.3 Document typed columns in the README.
- [x] 2.4 Sync the delta into `openspec/specs/` and archive the change.
