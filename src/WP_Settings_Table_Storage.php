<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Protect against redeclaration errors.
if (interface_exists('BGoewert\\WP_Settings\\WP_Settings_Table_Storage')) {
    return;
}

/**
 * Where a WP_Settings_Table keeps its rows.
 *
 * Every method addresses one row so that two requests changing different rows
 * cannot drop each other's work. Implementations that cannot write a single row
 * narrow that window rather than closing it.
 */
interface WP_Settings_Table_Storage
{
    /**
     * Retrieve every row, keyed by row id, in the order it should render.
     *
     * @return array
     */
    public function get_rows();

    /**
     * Retrieve one row by id.
     *
     * @param string $row_id Row id.
     * @return array|null Null when the row is not stored.
     */
    public function get_row($row_id);

    /**
     * Write one row, replacing it if the id is already stored.
     *
     * @param string $row_id Row id.
     * @param array  $row    Row data.
     */
    public function save_row($row_id, array $row);

    /**
     * Remove one row.
     *
     * @param string $row_id Row id.
     */
    public function delete_row($row_id);

    /**
     * Write one row's status value, leaving the rest of the row alone.
     *
     * @param string $row_id Row id.
     * @param mixed  $value  Stored status value.
     */
    public function set_row_status($row_id, $value);

    /**
     * Replace the whole set. For imports and migrations, not for editing.
     *
     * @param array $rows Rows keyed by row id.
     */
    public function replace_rows(array $rows);
}
