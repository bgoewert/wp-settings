## MODIFIED Requirements

### Requirement: The database table adapter writes one database row per table row

`WP_Settings_Table_Custom_Table_Storage` SHALL keep each row in its own database row, keyed by its id column, with the row's data as JSON, its normalized status mirrored into an indexed column, and creation and update timestamps. A save SHALL be one `INSERT ... ON DUPLICATE KEY UPDATE`, which preserves the creation timestamp and cannot lose a row written concurrently. A delete SHALL be one `DELETE`. Reading one row SHALL be one `SELECT` for that id rather than a read of the whole set.

Rows SHALL come back in creation order, so a table that switches adapters renders in the order it did before. An adapter with no creation timestamp SHALL order by id instead.

The schema SHALL be installed on demand, guarded by a stored schema version so the check costs one autoloaded option read, and SHALL also be installable directly through `WP_Settings_Table::install_storage()` for an activation hook. The stored version SHALL be keyed to the configured shape, so changing the columns installs again without a manual bump.

#### Scenario: Saving a row that already exists

- **WHEN** a row is saved twice
- **THEN** one database row exists, its creation timestamp is the first write's and its update timestamp is the second's

#### Scenario: Reading a single row

- **WHEN** a handler reads one row by id
- **THEN** only that row is loaded, not the whole table

## ADDED Requirements

### Requirement: The consumer describes the columns the adapter writes

The adapter SHALL accept an optional argument array describing the table: a `columns` map of row key to column name for fields kept in a column of their own, renames for the id, status, JSON, creation and update columns, a `schema` giving the `CREATE TABLE` body verbatim, and `install` set false when the consumer owns the table. Every default SHALL be the schema the adapter shipped with, so an adapter constructed without the argument behaves as it did before.

A mapped field SHALL be written to and read from its own column, so SQL can filter, sort and delete on it. A field that is not mapped SHALL keep going into the JSON column, so a table gains columns without losing the rows already in it. A mapped value that is not a scalar SHALL stay in the JSON column, because a column holds one value.

Any column other than the id column SHALL be droppable. With no JSON column the mapped columns are the whole row. Naming the status key as a mapped column SHALL drop the mirrored status column, the status having a real one of its own.

With `install` false the adapter SHALL never issue `dbDelta`, including through `install_storage()`. Otherwise it SHALL install the supplied `schema`, or one generated from the configured columns.

#### Scenario: A field mapped to its own column

- **WHEN** a row is saved with a field named in `columns`
- **THEN** the value is in that column, not inside the JSON, and reading the row returns it

#### Scenario: A field the map does not name

- **WHEN** a row is saved with a field absent from `columns`
- **THEN** it round-trips through the JSON column as it always did

#### Scenario: The consumer owns the table

- **WHEN** an adapter constructed with `install` false reads, writes, or is asked to install
- **THEN** no `CREATE TABLE` is issued

#### Scenario: The shape changes

- **WHEN** an adapter with a different column map runs against an installed table
- **THEN** the schema is installed again
