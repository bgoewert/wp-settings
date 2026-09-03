## ADDED Requirements

### Requirement: A table reads and writes its rows through a storage adapter

`WP_Settings_Table` SHALL reach its rows only through an object implementing `WP_Settings_Table_Storage`, which exposes reading every row, reading one row by id, writing one row, deleting one row, writing one row's status, and replacing the whole set. A table SHALL accept the adapter as a `storage` argument, either as the string `option` or `table` or as an instance to use as-is, and SHALL default to `option`.

The adapter SHALL be built the first time it is needed rather than in the constructor, because the option or database table name carries the text domain and `set_text_domain()` runs after construction. Setting the text domain SHALL discard an adapter the table built for itself, and SHALL leave an adapter the consumer passed in alone.

#### Scenario: Table configured with no storage argument

- **WHEN** a `WP_Settings_Table` is constructed without `storage`
- **THEN** its rows are read from and written to the same prefixed option as before

#### Scenario: Text domain set after construction

- **WHEN** `set_text_domain()` runs after the table has already resolved its own adapter
- **THEN** the next read builds a new adapter against the prefixed name

#### Scenario: Consumer supplies an adapter

- **WHEN** `storage` is an object implementing `WP_Settings_Table_Storage`
- **THEN** the table uses that object and never replaces it

### Requirement: A mutation touches only the rows it changes

Saving, deleting, toggling, setting a status and every bulk action SHALL read and write only the rows named in the request. No handler SHALL read the whole row set and write the whole row set back, so that two requests changing different rows cannot drop each other's work.

#### Scenario: Two requests add different rows

- **WHEN** two requests each save a new row, each having read the table before either wrote
- **THEN** both rows are present afterwards

#### Scenario: Bulk action over a selection

- **WHEN** a bulk delete or bulk status change runs over a selection
- **THEN** each selected row is written on its own, and rows outside the selection are never written

### Requirement: The option adapter keeps the existing storage shape

`WP_Settings_Table_Option_Storage` SHALL store rows as one associative array in one option, keyed by row id, in insertion order. Reading a value that is not an array SHALL yield an empty set rather than an error, and reading a row that is not present SHALL yield `null`.

This adapter cannot make a mutation atomic — the option API has no per-key write — so it narrows the window without closing it. A table written by anything other than an admin at a keyboard SHALL use the database table adapter.

#### Scenario: Option holds a non-array value

- **WHEN** the option contains a string
- **THEN** the table reads an empty set

### Requirement: The database table adapter writes one database row per table row

`WP_Settings_Table_Custom_Table_Storage` SHALL keep each row in its own database row, keyed by `row_id`, with the row's data as JSON, its normalized status mirrored into an indexed column, and creation and update timestamps. A save SHALL be one `INSERT ... ON DUPLICATE KEY UPDATE`, which preserves the creation timestamp and cannot lose a row written concurrently. A delete SHALL be one `DELETE`. Reading one row SHALL be one `SELECT` for that id rather than a read of the whole set.

Rows SHALL come back in creation order, so a table that switches adapters renders in the order it did before.

The schema SHALL be installed on demand, guarded by a stored schema version so the check costs one autoloaded option read, and SHALL also be installable directly through `WP_Settings_Table::install_storage()` for an activation hook.

#### Scenario: Saving a row that already exists

- **WHEN** a row is saved twice
- **THEN** one database row exists, its creation timestamp is the first write's and its update timestamp is the second's

#### Scenario: Reading a single row

- **WHEN** a handler reads one row by id
- **THEN** only that row is loaded, not the whole table

### Requirement: A generated row id does not collide with one already stored

`generate_row_id()` SHALL keep its `slug-timestamp` shape and SHALL append a numeric suffix while that id is already in storage, so two records created in the same second under the same name get distinct ids instead of one overwriting the other.

#### Scenario: Two rows named the same in the same second

- **WHEN** a second row is saved with a name and second that already produced an id
- **THEN** the second row is stored under a suffixed id and both rows are present
