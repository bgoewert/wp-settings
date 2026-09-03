## Why

`WP_Settings_Table` keeps every row of a table in one option. Each mutation reads the whole array, changes one key, and writes the whole array back ([#25](https://github.com/bgoewert/wp-settings/issues/25)). That is the right shape for a table a human edits one row at a time through the modal, and the wrong shape for a table something else writes.

A webhook mirroring records into WordPress fires once per record. Two requests arriving together each read the same array, each add their own row, and the second write drops the first. Nothing errors, and nothing short of counting rows against the source notices. The row id compounds it: `sanitize_title($name) . '-' . time()` gives two records created in the same second with the same name the same id, so one silently overwrites the other.

## What Changes

- A `WP_Settings_Table_Storage` interface behind the row reads and writes, so a table's rows can live somewhere other than one option.
- `WP_Settings_Table_Option_Storage`, the default: the existing option array, so nothing changes for a table that does not ask for anything else.
- `WP_Settings_Table_Custom_Table_Storage`, opt-in with `'storage' => 'table'`: one database table, one database row per table row, written with a single upsert per save and a single delete per delete. It also buys the per-row read the option array cannot do — a lookup by id no longer loads every row into PHP.
- Every handler on `WP_Settings_Table` — save, delete, toggle, toggle status, bulk — reads and writes the rows it touches rather than the whole set.
- Generated row ids are checked against storage and given a numeric suffix on collision.

## Capabilities

### New Capabilities

- `table-row-storage`: where a settings table keeps its rows, and what each mutation is allowed to read and write.

### Modified Capabilities

<!-- none -->

## Impact

- **Code**: `src/WP_Settings_Table.php`, plus three new files under `src/`.
- **Consumers**: none required. A table with no `storage` argument keeps the option array and the same option name. Moving one to `'storage' => 'table'` means installing the schema (`WP_Settings_Table::install_storage()`) and migrating the existing option, which this change does not do for you.
