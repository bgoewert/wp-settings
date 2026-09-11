<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Protect against redeclaration errors.
if (class_exists('BGoewert\\WP_Settings\\WP_Settings_Table_Custom_Table_Storage')) {
    return;
}

/**
 * Table rows in their own database table, one database row each.
 *
 * Opt in with `'storage' => 'table'`. A save is one upsert and a delete is one
 * DELETE, so two requests adding different rows cannot drop each other the way
 * the option array does. A lookup by id is one SELECT rather than a read of
 * every row.
 *
 * By default the whole row is one JSON blob. A consumer whose table already has
 * typed columns — or who wants SQL on a field, a retention DELETE on a date,
 * a search on a name — maps those fields to columns instead, and can hand over
 * its own schema or keep ownership of the table entirely.
 */
class WP_Settings_Table_Custom_Table_Storage implements WP_Settings_Table_Storage
{
    /**
     * Schema version. Bump to make installed tables run dbDelta again.
     */
    const SCHEMA_VERSION = '1';

    /**
     * Unprefixed table name.
     *
     * @var string
     */
    protected $name;

    /**
     * Status key in each row.
     *
     * @var string
     */
    protected $status_key;

    /**
     * Row key => column name, for fields kept in their own column.
     *
     * @var array
     */
    protected $columns = array();

    /**
     * Column holding the row id.
     *
     * @var string
     */
    protected $id_column = 'row_id';

    /**
     * Column mirroring the normalized status, or null for none.
     *
     * @var string|null
     */
    protected $status_column = 'status';

    /**
     * Column holding everything not mapped to a column of its own, as JSON, or
     * null when the table's columns are the whole row.
     *
     * @var string|null
     */
    protected $data_column = 'data';

    /**
     * Column holding the creation timestamp, or null for none.
     *
     * @var string|null
     */
    protected $created_column = 'created_at';

    /**
     * Column holding the last-write timestamp, or null for none.
     *
     * @var string|null
     */
    protected $updated_column = 'updated_at';

    /**
     * CREATE TABLE body supplied by the consumer, or null for the generated one.
     *
     * @var string|null
     */
    protected $schema;

    /**
     * Whether this adapter may create and upgrade the table.
     *
     * @var bool
     */
    protected $installs = true;

    /**
     * @param string $name       Table name, already prefixed with the text domain.
     * @param string $status_key Status key in each row.
     * @param array  $args       Optional: `columns` (row key => column name, or
     *                           a list of names used as-is), `id_column`,
     *                           `status_column`, `data_column`,
     *                           `created_column`, `updated_column` (null drops
     *                           the column), `schema` (CREATE TABLE body) and
     *                           `install` (false when the consumer owns the
     *                           table).
     */
    public function __construct($name, $status_key = 'enabled', array $args = array())
    {
        // MySQL allows 64 characters, and $wpdb->prefix eats some of them.
        $this->name       = substr(preg_replace('/[^a-z0-9_]/', '_', strtolower($name)), 0, 48);
        $this->status_key = $status_key;
        $this->columns    = $this->normalize_columns($args['columns'] ?? array());

        foreach (array('status_column', 'data_column', 'created_column', 'updated_column') as $key) {
            if (array_key_exists($key, $args)) {
                $this->$key = $this->column_name($args[$key]);
            }
        }

        // The row has to be addressable, so this one column cannot be dropped.
        if (isset($args['id_column']) && $this->column_name($args['id_column']) !== null) {
            $this->id_column = $this->column_name($args['id_column']);
        }

        if (isset($args['schema']) && is_string($args['schema'])) {
            $this->schema = $args['schema'];
        }

        if (array_key_exists('install', $args)) {
            $this->installs = (bool) $args['install'];
        }

        // Mirroring the status is pointless once the status has a column of its own.
        if (isset($this->columns[$this->status_key])) {
            $this->status_column = null;
        }
    }

    public function get_rows()
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $results = $wpdb->get_results(
            'SELECT ' . $this->select_list() . " FROM `{$table}` ORDER BY " . $this->order_by(),
            \ARRAY_A
        );

        if (!is_array($results)) {
            return array();
        }

        $rows = array();
        foreach ($results as $result) {
            $row = $this->decode_row($result);
            if ($row !== null) {
                $rows[$result[$this->id_column]] = $row;
            }
        }

        return $rows;
    }

    public function get_row($row_id)
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . $this->select_list() . " FROM `{$table}` WHERE {$this->id_column} = %s",
                $row_id
            ),
            \ARRAY_A
        );

        return is_array($result) ? $this->decode_row($result) : null;
    }

    public function save_row($row_id, array $row)
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $values  = $this->encode_row($row_id, $row);
        $columns = array_keys($values);
        $updates = array();

        foreach ($columns as $column) {
            // The id is the key, and the creation time belongs to the first write.
            if ($column === $this->id_column || $column === $this->created_column) {
                continue;
            }

            $updates[] = "{$column} = VALUES({$column})";
        }

        // One statement, so a concurrent write to another row cannot lose this
        // one. ON DUPLICATE KEY rather than REPLACE to keep created_at.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO `{$table}` (" . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', array_fill(0, count($columns), '%s')) . ')
                 ON DUPLICATE KEY UPDATE ' . implode(', ', $updates),
                ...array_values($values)
            )
        );
    }

    public function delete_row($row_id)
    {
        $wpdb = $this->wpdb();

        $this->maybe_install();

        $wpdb->delete($this->table_name(), array($this->id_column => $row_id), array('%s'));
    }

    public function set_row_status($row_id, $value)
    {
        $row = $this->get_row($row_id);

        if ($row === null) {
            return;
        }

        $row[$this->status_key] = $value;
        $this->save_row($row_id, $row);
    }

    public function replace_rows(array $rows)
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $wpdb->query("DELETE FROM `{$table}`");

        foreach ($rows as $row_id => $row) {
            if (is_array($row)) {
                $this->save_row((string) $row_id, $row);
            }
        }
    }

    /**
     * Create or upgrade the table. Safe to call from an activation hook.
     */
    public function install()
    {
        // Nothing to do when the consumer owns the table.
        if (!$this->installs) {
            return;
        }

        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE `{$table}` (\n" . $this->schema_body() . "\n) {$charset_collate}";

        if (!function_exists('dbDelta')) {
            require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        \dbDelta($sql);
        \update_option($this->version_option(), $this->schema_version());
    }

    /**
     * Install the schema unless the stored version already matches.
     *
     * The guard is an autoloaded option read, so this costs nothing per request
     * once installed. It also means a consumer that forgets the activation hook
     * gets a working table instead of a fatal.
     */
    protected function maybe_install()
    {
        if (!$this->installs) {
            return;
        }

        if (\get_option($this->version_option()) === $this->schema_version()) {
            return;
        }

        $this->install();
    }

    /**
     * Option holding the installed schema version.
     *
     * @return string
     */
    protected function version_option()
    {
        return $this->name . '_schema_version';
    }

    /**
     * Installed version, keyed to the shape as well as the constant, so changing
     * the columns runs dbDelta again without a manual bump.
     *
     * @return string
     */
    protected function schema_version()
    {
        return self::SCHEMA_VERSION . '-' . substr(md5($this->schema_body()), 0, 8);
    }

    /**
     * Column and key definitions for CREATE TABLE.
     *
     * @return string
     */
    protected function schema_body()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $lines = array("{$this->id_column} varchar(191) NOT NULL");

        // A mapped column with no schema of its own gets the widest type, since
        // nothing here knows what the consumer keeps in it.
        foreach ($this->columns as $column) {
            $lines[] = "{$column} longtext NOT NULL";
        }

        if ($this->status_column !== null) {
            $lines[] = "{$this->status_column} varchar(64) NOT NULL DEFAULT ''";
        }

        if ($this->data_column !== null) {
            $lines[] = "{$this->data_column} longtext NOT NULL";
        }

        foreach (array($this->created_column, $this->updated_column) as $column) {
            if ($column !== null) {
                $lines[] = "{$column} datetime NOT NULL";
            }
        }

        $lines[] = "PRIMARY KEY  ({$this->id_column})";

        if ($this->status_column !== null) {
            $lines[] = "KEY {$this->status_column} ({$this->status_column})";
        }

        return '    ' . implode(",\n    ", $lines);
    }

    /**
     * Columns a read needs: the id, every mapped field, and the JSON remainder.
     *
     * @return string
     */
    protected function select_list()
    {
        $columns = array_merge(array($this->id_column), array_values($this->columns));

        if ($this->data_column !== null) {
            $columns[] = $this->data_column;
        }

        return implode(', ', array_unique($columns));
    }

    /**
     * Creation order, so a table that switches adapters renders as it did.
     *
     * @return string
     */
    protected function order_by()
    {
        if ($this->created_column === null) {
            return "{$this->id_column} ASC";
        }

        return "{$this->created_column} ASC, {$this->id_column} ASC";
    }

    /**
     * Fully prefixed table name.
     *
     * @return string
     */
    protected function table_name()
    {
        return $this->wpdb()->prefix . $this->name;
    }

    /**
     * @return \wpdb
     */
    protected function wpdb()
    {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Current time in MySQL format.
     *
     * @return string
     */
    protected function now()
    {
        return \gmdate('Y-m-d H:i:s');
    }

    /**
     * Status key for the indexed column, so a query can filter without parsing JSON.
     *
     * @param array $row Row data.
     * @return string
     */
    protected function status_of(array $row)
    {
        $value = $row[$this->status_key] ?? null;

        if (is_bool($value)) {
            return $value ? 'enabled' : 'disabled';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Column name => value for one row, in the order the INSERT writes them.
     *
     * @param string $row_id Row id.
     * @param array  $row    Row data.
     * @return array
     */
    protected function encode_row($row_id, array $row)
    {
        $values    = array($this->id_column => (string) $row_id);
        $remainder = $row;

        foreach ($this->columns as $key => $column) {
            $value = $row[$key] ?? null;
            unset($remainder[$key]);

            if ($value === null || is_scalar($value)) {
                $values[$column] = $this->column_value($value);
                continue;
            }

            // A column holds one value. Anything larger stays in the JSON
            // remainder, where it round-trips, and the column is left empty.
            $values[$column] = $this->data_column === null ? $this->encode((array) $value) : '';

            if ($this->data_column !== null) {
                $remainder[$key] = $value;
            }
        }

        if ($this->data_column !== null) {
            $values[$this->data_column] = $this->encode($remainder);
        }

        if ($this->status_column !== null) {
            $values[$this->status_column] = $this->status_of($row);
        }

        $now = $this->now();

        foreach (array($this->created_column, $this->updated_column) as $column) {
            if ($column !== null) {
                $values[$column] = $now;
            }
        }

        return $values;
    }

    /**
     * One stored row back into row data.
     *
     * The JSON remainder is merged last, so a value too large for its column
     * comes back from where it was actually written.
     *
     * @param array $result Column name => stored value.
     * @return array|null
     */
    protected function decode_row(array $result)
    {
        $row = array();

        foreach ($this->columns as $key => $column) {
            if (array_key_exists($column, $result)) {
                $row[$key] = $result[$column];
            }
        }

        if ($this->data_column === null) {
            return $row;
        }

        $data = $this->decode($result[$this->data_column] ?? '');

        if ($data === null) {
            return $this->columns === array() ? null : $row;
        }

        return array_merge($row, $data);
    }

    /**
     * @param array $row Row data.
     * @return string
     */
    protected function encode(array $row)
    {
        $json = \wp_json_encode($row);
        return is_string($json) ? $json : '{}';
    }

    /**
     * @param string $data Stored JSON.
     * @return array|null
     */
    protected function decode($data)
    {
        $row = json_decode((string) $data, true);
        return is_array($row) ? $row : null;
    }

    /**
     * Scalar bound to a column, with booleans written as MySQL reads them back.
     *
     * @param mixed $value Row value.
     * @return string
     */
    protected function column_value($value)
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param array $columns Row key => column name, or a list of column names.
     * @return array
     */
    protected function normalize_columns($columns)
    {
        if (!is_array($columns)) {
            return array();
        }

        $map = array();

        foreach ($columns as $key => $column) {
            $name = $this->column_name($column);

            if ($name === null) {
                continue;
            }

            $map[is_int($key) ? $name : (string) $key] = $name;
        }

        return $map;
    }

    /**
     * Identifier safe to interpolate, since a column name cannot be prepared.
     *
     * @param mixed $column Configured column name.
     * @return string|null Null when there is no such column.
     */
    protected function column_name($column)
    {
        if (!is_string($column) && !is_numeric($column)) {
            return null;
        }

        $name = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $column));

        return $name === '' ? null : $name;
    }
}
