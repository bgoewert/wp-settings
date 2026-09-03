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
     * @param string $name       Table name, already prefixed with the text domain.
     * @param string $status_key Status key in each row.
     */
    public function __construct($name, $status_key = 'enabled')
    {
        // MySQL allows 64 characters, and $wpdb->prefix eats some of them.
        $this->name       = substr(preg_replace('/[^a-z0-9_]/', '_', strtolower($name)), 0, 48);
        $this->status_key = $status_key;
    }

    public function get_rows()
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $results = $wpdb->get_results(
            "SELECT row_id, data FROM `{$table}` ORDER BY created_at ASC, row_id ASC",
            \ARRAY_A
        );

        if (!is_array($results)) {
            return array();
        }

        $rows = array();
        foreach ($results as $result) {
            $row = $this->decode($result['data'] ?? '');
            if ($row !== null) {
                $rows[$result['row_id']] = $row;
            }
        }

        return $rows;
    }

    public function get_row($row_id)
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $this->maybe_install();

        $data = $wpdb->get_var(
            $wpdb->prepare("SELECT data FROM `{$table}` WHERE row_id = %s", $row_id)
        );

        return $data === null ? null : $this->decode($data);
    }

    public function save_row($row_id, array $row)
    {
        $wpdb  = $this->wpdb();
        $table = $this->table_name();
        $now   = $this->now();

        $this->maybe_install();

        // One statement, so a concurrent write to another row cannot lose this
        // one. ON DUPLICATE KEY rather than REPLACE to keep created_at.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO `{$table}` (row_id, status, data, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), data = VALUES(data), updated_at = VALUES(updated_at)",
                $row_id,
                $this->status_of($row),
                $this->encode($row),
                $now,
                $now
            )
        );
    }

    public function delete_row($row_id)
    {
        $wpdb = $this->wpdb();

        $this->maybe_install();

        $wpdb->delete($this->table_name(), array('row_id' => $row_id), array('%s'));
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
        $wpdb  = $this->wpdb();
        $table = $this->table_name();

        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE `{$table}` (
            row_id varchar(191) NOT NULL,
            status varchar(64) NOT NULL DEFAULT '',
            data longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (row_id),
            KEY status (status)
        ) {$charset_collate}";

        if (!function_exists('dbDelta')) {
            require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        \dbDelta($sql);
        \update_option($this->version_option(), self::SCHEMA_VERSION);
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
        if (\get_option($this->version_option()) === self::SCHEMA_VERSION) {
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
}
