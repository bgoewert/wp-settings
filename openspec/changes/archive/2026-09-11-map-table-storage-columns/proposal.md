## Why

`WP_Settings_Table_Custom_Table_Storage` hardcodes its schema — `row_id`, `status`, one JSON `data` blob, `created_at`, `updated_at` ([#26](https://github.com/bgoewert/wp-settings/issues/26)). A consumer that already has a typed table cannot use it, and a consumer that adopts it gives up SQL on its own fields.

The case that hit this: a blocked-mail log with typed columns for recipient, subject, reason and creation time. Retention wants `DELETE ... WHERE created < %s` and the log is searched by recipient. Both become "decode every row in PHP" once those fields live inside `data`. The only way to keep the typed table was to implement `WP_Settings_Table_Storage` by hand, which is the work the class exists to remove.

## What Changes

- The adapter takes an optional argument array: `columns` mapping row keys to columns of their own, `id_column`/`status_column`/`data_column`/`created_column`/`updated_column` to rename or drop a column, `schema` to supply the `CREATE TABLE` body, and `install` to hand schema ownership back to the consumer.
- Reads and writes name the mapped columns. Anything unmapped keeps going into the JSON column, so an existing table gains columns without losing rows and a default-configured adapter behaves exactly as before.
- The stored schema version is keyed to the shape, so changing the columns runs `dbDelta` again without a manual bump.

## Capabilities

### New Capabilities

<!-- none -->

### Modified Capabilities

- `table-row-storage`: the database table adapter's schema becomes the consumer's to describe.

## Impact

- **Code**: `src/WP_Settings_Table_Custom_Table_Storage.php`, plus the `$wpdb` stand-in in `tests/bootstrap.php`, which hardcoded the column list it parsed.
- **Consumers**: none required. The constructor's third argument is optional and its defaults are the existing schema.
