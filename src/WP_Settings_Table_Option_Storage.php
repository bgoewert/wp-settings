<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Protect against redeclaration errors.
if (class_exists('BGoewert\\WP_Settings\\WP_Settings_Table_Option_Storage')) {
    return;
}

/**
 * Table rows in one option, keyed by row id.
 *
 * The default, and the shape every table used before adapters existed. The
 * option API has no per-key write, so each method here is still a
 * read-modify-write of the whole array: two requests arriving together can
 * drop each other's row. A table something other than an admin writes wants
 * WP_Settings_Table_Custom_Table_Storage instead.
 */
class WP_Settings_Table_Option_Storage implements WP_Settings_Table_Storage
{
    /**
     * Option name, already prefixed.
     *
     * @var string
     */
    protected $option;

    /**
     * Status key in each row.
     *
     * @var string
     */
    protected $status_key;

    /**
     * @param string $option     Option name, already prefixed.
     * @param string $status_key Status key in each row.
     */
    public function __construct($option, $status_key = 'enabled')
    {
        $this->option     = $option;
        $this->status_key = $status_key;
    }

    public function get_rows()
    {
        $rows = \get_option($this->option, array());
        return is_array($rows) ? $rows : array();
    }

    public function get_row($row_id)
    {
        $rows = $this->get_rows();

        if (!isset($rows[$row_id]) || !is_array($rows[$row_id])) {
            return null;
        }

        return $rows[$row_id];
    }

    public function save_row($row_id, array $row)
    {
        $rows          = $this->get_rows();
        $rows[$row_id] = $row;
        \update_option($this->option, $rows);
    }

    public function delete_row($row_id)
    {
        $rows = $this->get_rows();

        if (!array_key_exists($row_id, $rows)) {
            return;
        }

        unset($rows[$row_id]);
        \update_option($this->option, $rows);
    }

    public function set_row_status($row_id, $value)
    {
        $rows = $this->get_rows();

        if (!isset($rows[$row_id]) || !is_array($rows[$row_id])) {
            return;
        }

        $rows[$row_id][$this->status_key] = $value;
        \update_option($this->option, $rows);
    }

    public function replace_rows(array $rows)
    {
        \update_option($this->option, $rows);
    }
}
