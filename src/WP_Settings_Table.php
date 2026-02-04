<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Reusable settings table with modal editing and AJAX CRUD.
 */
class WP_Settings_Table
{
    /**
     * Table id.
     *
     * @var string
     */
    protected $id;

    /**
     * Tab slug to render in.
     *
     * @var string
     */
    protected $tab;

    /**
     * Option name (without text domain prefix).
     *
     * @var string
     */
    protected $option;

    /**
     * Table title.
     *
     * @var string
     */
    protected $title;

    /**
     * Table description.
     *
     * @var string|null
     */
    protected $description;

    /**
     * Column definitions.
     *
     * @var array
     */
    protected $columns;

    /**
     * Field definitions (WP_Setting instances).
     *
     * @var WP_Setting[]
     */
    protected $fields;

    /**
     * Status definitions.
     *
     * @var array
     */
    protected $statuses;

    /**
     * Status key in each row.
     *
     * @var string
     */
    protected $status_key;

    /**
     * Required capability.
     *
     * @var string
     */
    protected $capability;

    /**
     * Plugin text domain.
     *
     * @var string
     */
    protected $text_domain;

    /**
     * Row id key in each row.
     *
     * @var string
     */
    protected $row_id_key;

    /**
     * Bulk actions list.
     *
     * @var array|null
     */
    protected $bulk_actions;

    /**
     * Whether to show individual status toggle buttons.
     *
     * @var bool
     */
    protected $show_status_toggle;

    /**
     * Create a settings table.
     *
     * @param array $args Table configuration.
     */
    public function __construct(array $args)
    {
        $this->id          = $args['id'] ?? '';
        $this->tab         = $args['tab'] ?? '';
        $this->option      = $args['option'] ?? $this->id;
        $this->title       = $args['title'] ?? '';
        $this->description = $args['description'] ?? null;
        $this->columns     = $args['columns'] ?? array();
        $this->fields      = $args['fields'] ?? array();
        $this->statuses    = $args['statuses'] ?? array(
            'enabled' => array('label' => 'Enabled'),
            'disabled' => array('label' => 'Disabled'),
        );
        $this->status_key  = $args['status_key'] ?? 'enabled';
        $this->capability  = $args['capability'] ?? 'manage_options';
        $this->row_id_key  = $args['row_id_key'] ?? 'id';
        $this->bulk_actions = $args['bulk_actions'] ?? null;
        $this->show_status_toggle = $args['show_status_toggle'] ?? false;
    }

    /**
     * Set the text domain from the parent WP_Settings.
     *
     * @param string $text_domain Plugin text domain.
     */
    public function set_text_domain($text_domain)
    {
        $this->text_domain = $text_domain;
    }

    /**
     * Initialize hooks.
     */
    public function init()
    {
        \add_action('wp_ajax_' . $this->get_ajax_action(), array($this, 'ajax_handler'));
    }

    /**
     * Check whether the table is assigned to a tab.
     *
     * @param string $tab Tab slug.
     * @return bool
     */
    public function handles_tab($tab)
    {
        return $this->tab === $tab;
    }

    /**
     * Render the table UI.
     *
     * @param string $page_slug Page slug for query args.
     * @param string $active_tab Active tab slug.
     */
    public function render($page_slug, $active_tab)
    {
        $rows = $this->get_rows();

        $edit_id = isset($_GET['edit']) ? \sanitize_text_field(\wp_unslash($_GET['edit'])) : '';
        $edit_row = $edit_id && isset($rows[$edit_id]) ? $rows[$edit_id] : array();

        $notice = $this->get_notice_message();
        if ($notice) {
            echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($notice) . '</p></div>';
        }

        echo '<div class="wps-table" data-table-id="' . \esc_attr($this->id) . '" data-ajax-action="' . \esc_attr($this->get_ajax_action()) . '" data-ajax-nonce="' . \esc_attr($this->get_nonce()) . '">';
        echo '<h2>' . \esc_html($this->title) . '</h2>';
        if ($this->description) {
            echo '<p>' . \esc_html($this->description) . '</p>';
        }

        echo '<button type="button" class="button button-primary wps-add-row">' . \esc_html__('Add New', 'wp-settings') . '</button>';

        $this->render_table_form($rows, $page_slug, $active_tab);
        $this->render_modal();
        $this->render_fallback_form($edit_row, $rows);
        $this->render_data_script($rows);

        echo '</div>';
    }

    /**
     * Handle non-AJAX submissions.
     *
     * @param string $active_tab Active tab slug.
     */
    public function handle_post($active_tab)
    {
        if ($this->tab !== $active_tab) {
            return;
        }

        if (!isset($_POST['wps_table_id']) || $_POST['wps_table_id'] !== $this->id) {
            return;
        }

        if (!isset($_POST['wps_table_nonce']) || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['wps_table_nonce'])), $this->get_nonce_action())) {
            return;
        }

        if (!\current_user_can($this->capability)) {
            return;
        }

        $action = isset($_POST['wps_table_action']) ? \sanitize_key(\wp_unslash($_POST['wps_table_action'])) : '';

        switch ($action) {
            case 'save':
                $this->handle_save($_POST);
                $this->redirect_with_notice('saved');
                break;
            case 'delete':
                $this->handle_delete($_POST);
                $this->redirect_with_notice('deleted');
                break;
            case 'toggle':
                $this->handle_toggle($_POST);
                $this->redirect_with_notice('saved');
                break;
            case 'bulk':
                $this->handle_bulk($_POST);
                $this->redirect_with_notice('saved');
                break;
        }
    }

    /**
     * AJAX handler for table actions.
     */
    public function ajax_handler()
    {
        if (!isset($_POST['nonce']) || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['nonce'])), $this->get_nonce_action())) {
            \wp_send_json_error(array('message' => __('Invalid nonce', 'wp-settings')));
        }

        if (!\current_user_can($this->capability)) {
            \wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-settings')));
        }

        $subaction = isset($_POST['subaction']) ? \sanitize_key(\wp_unslash($_POST['subaction'])) : '';

        switch ($subaction) {
            case 'save':
                $row_id = $this->handle_save($_POST);
                \wp_send_json_success(array('row_id' => $row_id));
                break;
            case 'delete':
                $this->handle_delete($_POST);
                \wp_send_json_success();
                break;
            case 'toggle':
                $this->handle_toggle($_POST);
                \wp_send_json_success();
                break;
            case 'toggle_status':
                $this->handle_toggle_status($_POST);
                \wp_send_json_success();
                break;
            case 'bulk':
                $this->handle_bulk($_POST);
                \wp_send_json_success();
                break;
        }

        \wp_send_json_error(array('message' => __('Unknown action', 'wp-settings')));
    }

    /**
     * Save or update a row.
     *
     * @param array $data Raw request data.
     * @return string Row id.
     */
    protected function handle_save(array $data)
    {
        $rows = $this->get_rows();
        $row_id = isset($data['row_id']) ? \sanitize_text_field(\wp_unslash($data['row_id'])) : '';

        $row = $this->sanitize_row($data);

        if (empty($row_id)) {
            $row_id = $this->generate_row_id($row);
        }

        $row[$this->row_id_key] = $row_id;
        $rows[$row_id] = $row;

        $this->update_rows($rows);

        return $row_id;
    }

    /**
     * Delete a row.
     *
     * @param array $data Raw request data.
     */
    protected function handle_delete(array $data)
    {
        $rows = $this->get_rows();
        $row_id = isset($data['row_id']) ? \sanitize_text_field(\wp_unslash($data['row_id'])) : '';

        if ($row_id && isset($rows[$row_id])) {
            unset($rows[$row_id]);
            $this->update_rows($rows);
        }
    }

    /**
     * Toggle a row status.
     *
     * @param array $data Raw request data.
     */
    protected function handle_toggle(array $data)
    {
        $rows = $this->get_rows();
        $row_id = isset($data['row_id']) ? \sanitize_text_field(\wp_unslash($data['row_id'])) : '';

        if (!$row_id || !isset($rows[$row_id])) {
            return;
        }

        $row = $rows[$row_id];
        $current = $this->normalize_status($row);

        if ($current === 'enabled') {
            $row[$this->status_key] = $this->status_value_for('disabled', $row);
        } else {
            $row[$this->status_key] = $this->status_value_for('enabled', $row);
        }

        $rows[$row_id] = $row;
        $this->update_rows($rows);
    }

    /**
     * Toggle a row status to a specific target status.
     *
     * @param array $data Raw request data.
     */
    protected function handle_toggle_status(array $data)
    {
        $rows = $this->get_rows();
        $row_id = isset($data['row_id']) ? \sanitize_text_field(\wp_unslash($data['row_id'])) : '';
        $target_status = isset($data['target_status']) ? \sanitize_key(\wp_unslash($data['target_status'])) : '';

        if (!$row_id || !isset($rows[$row_id]) || !$this->is_valid_status($target_status)) {
            return;
        }

        $row = $rows[$row_id];
        $row[$this->status_key] = $this->status_value_for($target_status, $row);

        $rows[$row_id] = $row;
        $this->update_rows($rows);
    }

    /**
     * Handle bulk actions.
     *
     * @param array $data Raw request data.
     */
    protected function handle_bulk(array $data)
    {
        $rows = $this->get_rows();
        $action = isset($data['bulk_action']) ? \sanitize_key(\wp_unslash($data['bulk_action'])) : '';
        $selected = isset($data['selected']) ? (array) $data['selected'] : array();
        $selected = array_map('\sanitize_text_field', array_map('\wp_unslash', $selected));

        if (empty($selected) || empty($action)) {
            return;
        }

        if ($action === 'delete') {
            foreach ($selected as $row_id) {
                unset($rows[$row_id]);
            }
            $this->update_rows($rows);
            return;
        }

        if ($this->is_valid_status($action)) {
            foreach ($selected as $row_id) {
                if (!isset($rows[$row_id])) {
                    continue;
                }
                $rows[$row_id][$this->status_key] = $this->status_value_for($action, $rows[$row_id]);
            }
            $this->update_rows($rows);
        }
    }

    /**
     * Sanitize a row using field definitions.
     *
     * @param array $data Raw request data.
     * @return array
     */
    protected function sanitize_row(array $data)
    {
        $row = array();

        foreach ($this->fields as $field) {
            // Handle fieldset and advanced fields with children.
            if (($field->type === 'fieldset' || $field->type === 'advanced') && !empty($field->children)) {
                foreach ($field->children as $child) {
                    $field_name = $child->name;
                    $raw_value = isset($data[$field_name]) ? \wp_unslash($data[$field_name]) : null;

                    if ($child->type === 'checkbox') {
                        $row[$field_name] = !empty($raw_value) && $raw_value !== '0' && $raw_value !== 0;
                        continue;
                    }

                    $row[$field_name] = $child->sanitize_value($raw_value);
                }
            } else {
                // Regular field sanitization.
                $field_name = $field->name;
                $raw_value = isset($data[$field_name]) ? \wp_unslash($data[$field_name]) : null;

                if ($field->type === 'checkbox') {
                    $row[$field_name] = !empty($raw_value) && $raw_value !== '0' && $raw_value !== 0;
                    continue;
                }

                $row[$field_name] = $field->sanitize_value($raw_value);
            }
        }

        if (!isset($row[$this->status_key])) {
            $row[$this->status_key] = $this->status_value_for('enabled', $row);
        }

        return $row;
    }

    /**
     * Render the table and bulk actions form.
     *
     * @param array  $rows       Table rows.
     * @param string $page_slug  Page slug.
     * @param string $active_tab Active tab.
     */
    protected function render_table_form(array $rows, $page_slug, $active_tab)
    {
        echo '<form method="post" class="wps-table-form">';
        \wp_nonce_field($this->get_nonce_action(), 'wps_table_nonce');
        echo '<input type="hidden" name="wps_table_id" value="' . \esc_attr($this->id) . '">';

        $this->render_bulk_actions();

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<td class="check-column"><input type="checkbox" class="wps-select-all"></td>';
        foreach ($this->columns as $column) {
            $label = $column['label'] ?? '';
            $key = $column['key'] ?? '';
            echo '<th scope="col" data-sort-key="' . \esc_attr($key) . '">' . \esc_html($label) . '</th>';
        }
        echo '<th scope="col">' . \esc_html__('Actions', 'wp-settings') . '</th>';
        echo '</tr></thead>';

        echo '<tbody>';
        if (empty($rows)) {
            $colspan = count($this->columns) + 2;
            echo '<tr><td colspan="' . \esc_attr($colspan) . '" style="text-align:center;padding:20px;">';
            echo \esc_html__('No items found. Click "Add New" to create one.', 'wp-settings');
            echo '</td></tr>';
        } else {
            foreach ($rows as $row_id => $row) {
                echo '<tr data-row-id="' . \esc_attr($row_id) . '">';
                echo '<th scope="row" class="check-column"><input type="checkbox" name="selected[]" value="' . \esc_attr($row_id) . '"></th>';
                foreach ($this->columns as $column) {
                    $cell = $this->render_cell($column, $row, $row_id);
                    echo '<td>' . $cell . '</td>';
                }
                echo '<td class="wps-actions">';
                $edit_url = '?page=' . rawurlencode($page_slug) . '&tab=' . rawurlencode($active_tab) . '&edit=' . rawurlencode($row_id);
                echo '<a href="' . \esc_url($edit_url) . '" class="button wps-edit-row" data-row-id="' . \esc_attr($row_id) . '">' . \esc_html__('Edit', 'wp-settings') . '</a> ';

                // Show status toggle button if enabled.
                if ($this->show_status_toggle) {
                    $current_status = $this->normalize_status($row);
                    $is_enabled = ($current_status === 'enabled');
                    $toggle_text = $is_enabled ? __('Disable', 'wp-settings') : __('Enable', 'wp-settings');
                    $target_status = $is_enabled ? 'disabled' : 'enabled';
                    echo '<button type="button" class="button wps-toggle-status" data-row-id="' . \esc_attr($row_id) . '" data-target-status="' . \esc_attr($target_status) . '">' . \esc_html($toggle_text) . '</button> ';
                }

                echo '<button type="button" class="button wps-delete-row" data-row-id="' . \esc_attr($row_id) . '" data-row-action="delete">' . \esc_html__('Delete', 'wp-settings') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        echo '</form>';
    }

    /**
     * Render bulk actions controls.
     */
    protected function render_bulk_actions()
    {
        $actions = $this->get_bulk_actions();

        echo '<div class="wps-bulk-actions">';
        echo '<select name="bulk_action" class="wps-bulk-action-select">';
        echo '<option value="">' . \esc_html__('Bulk actions', 'wp-settings') . '</option>';
        foreach ($actions as $action_key => $action_label) {
            echo '<option value="' . \esc_attr($action_key) . '">' . \esc_html($action_label) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button wps-bulk-apply" name="wps_table_action" value="bulk">' . \esc_html__('Apply', 'wp-settings') . '</button>';
        echo '</div>';
    }

    /**
     * Render a cell value based on column config.
     *
     * @param array $column Column config.
     * @param array $row    Row data.
     * @return string
     */
    protected function render_cell(array $column, array $row, $row_id)
    {
        if (isset($column['render']) && is_callable($column['render'])) {
            return (string) call_user_func($column['render'], $row);
        }

        if (isset($column['type']) && $column['type'] === 'status') {
            return $this->render_status_cell($row, $row_id);
        }

        $key = $column['field'] ?? ($column['key'] ?? '');
        $value = isset($row[$key]) ? $row[$key] : '';

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return \esc_html($value);
    }

    /**
     * Render the status cell.
     *
     * @param array $row Row data.
     * @return string
     */
    protected function render_status_cell(array $row, $row_id)
    {
        $status = $this->normalize_status($row);
        $label = $this->statuses[$status]['label'] ?? ucfirst($status);
        $class = $this->statuses[$status]['class'] ?? $status;

        if ($this->is_valid_status('enabled') && $this->is_valid_status('disabled')) {
            $toggle_button = '<button type="button" class="wps-status-toggle" data-row-id="' . \esc_attr($row_id) . '">' . \esc_html($label) . '</button>';
        } else {
            $toggle_button = \esc_html($label);
        }

        return '<span class="wps-status ' . \esc_attr($class) . '">' . $toggle_button . '</span>';
    }

    /**
     * Render the modal form.
     */
    protected function render_modal()
    {
        echo '<div class="wps-modal wps-modal-hidden">';
        echo '<div class="wps-modal-content">';
        echo '<div class="wps-modal-header">';
        echo '<h2 class="wps-modal-title">' . \esc_html__('Add Item', 'wp-settings') . '</h2>';
        echo '<button type="button" class="wps-modal-close" aria-label="' . \esc_attr__('Close', 'wp-settings') . '">&times;</button>';
        echo '</div>';
        echo '<form class="wps-modal-form">';
        echo '<input type="hidden" name="row_id" value="">';
        echo '<table class="form-table">';

        foreach ($this->fields as $field) {
            // Handle fieldset fields with children.
            if ($field->type === 'fieldset' && !empty($field->children)) {
                echo '<tr><td colspan="2">';
                echo '<fieldset style="border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">';
                echo '<legend style="padding: 0 10px; font-weight: 600;">' . \esc_html($field->title) . '</legend>';

                if ($field->description) {
                    echo '<p class="description">' . \esc_html($field->description) . '</p>';
                }

                // Render children inside fieldset.
                foreach ($field->children as $child) {
                    $row_attrs = 'data-field="' . \esc_attr($child->name) . '"';
                    if ($child->has_conditions()) {
                        $row_attrs .= ' data-conditions="' . \esc_attr($child->get_conditions_json()) . '"';
                    }
                    echo '<div style="margin: 10px 0;" ' . $row_attrs . '>';
                    echo '<label for="' . \esc_attr($child->name) . '" style="display: block; font-weight: 600; margin-bottom: 5px;">' . \esc_html($child->title) . '</label>';
                    $child->render_unbound(null, $child->name, $child->name);
                    echo '</div>';
                }

                echo '</fieldset>';
                echo '</td></tr>';
            } elseif ($field->type === 'advanced' && !empty($field->children)) {
                // Handle advanced fields with children.
                $collapsed = $field->args['collapsed'] ?? true;
                $open_attr = $collapsed ? '' : ' open';

                echo '<tr><td colspan="2">';
                echo '<details style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"' . $open_attr . '>';
                echo '<summary style="cursor: pointer; font-weight: 600; font-size: 14px;">' . \esc_html($field->title) . '</summary>';
                echo '<div style="margin-top: 15px; padding-left: 10px;">';

                if ($field->description) {
                    echo '<p class="description">' . \esc_html($field->description) . '</p>';
                }

                // Render children inside advanced field.
                foreach ($field->children as $child) {
                    $row_attrs = 'data-field="' . \esc_attr($child->name) . '"';
                    if ($child->has_conditions()) {
                        $row_attrs .= ' data-conditions="' . \esc_attr($child->get_conditions_json()) . '"';
                    }
                    echo '<div style="margin: 10px 0;" ' . $row_attrs . '>';
                    echo '<label for="' . \esc_attr($child->name) . '" style="display: block; font-weight: 600; margin-bottom: 5px;">' . \esc_html($child->title) . '</label>';
                    $child->render_unbound(null, $child->name, $child->name);
                    echo '</div>';
                }

                echo '</div></details>';
                echo '</td></tr>';
            } else {
                // Regular field rendering.
                $row_attrs = 'data-field="' . \esc_attr($field->name) . '"';
                if ($field->has_conditions()) {
                    $row_attrs .= ' data-conditions="' . \esc_attr($field->get_conditions_json()) . '"';
                }
                echo '<tr ' . $row_attrs . '>';
                echo '<th scope="row"><label for="' . \esc_attr($field->name) . '">' . \esc_html($field->title) . '</label></th>';
                echo '<td>';
                $field->render_unbound(null, $field->name, $field->name);
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</table>';
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . \esc_html__('Save', 'wp-settings') . '</button>';
        echo '<button type="button" class="button wps-modal-cancel">' . \esc_html__('Cancel', 'wp-settings') . '</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render non-JS fallback form.
     *
     * @param array $edit_row Row data for editing.
     * @param array $rows     All rows.
     */
    protected function render_fallback_form(array $edit_row, array $rows)
    {
        echo '<div class="wps-fallback-form">';
        echo '<h3>' . \esc_html__('Add / Edit Item', 'wp-settings') . '</h3>';
        echo '<form method="post">';
        \wp_nonce_field($this->get_nonce_action(), 'wps_table_nonce');
        echo '<input type="hidden" name="wps_table_id" value="' . \esc_attr($this->id) . '">';
        echo '<input type="hidden" name="wps_table_action" value="save">';
        echo '<input type="hidden" name="row_id" value="' . \esc_attr($edit_row[$this->row_id_key] ?? '') . '">';
        echo '<table class="form-table">';

        foreach ($this->fields as $field) {
            // Handle fieldset fields with children.
            if ($field->type === 'fieldset' && !empty($field->children)) {
                echo '<tr><td colspan="2">';
                echo '<fieldset style="border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">';
                echo '<legend style="padding: 0 10px; font-weight: 600;">' . \esc_html($field->title) . '</legend>';

                if ($field->description) {
                    echo '<p class="description">' . \esc_html($field->description) . '</p>';
                }

                // Render children inside fieldset.
                foreach ($field->children as $child) {
                    $value = $edit_row[$child->name] ?? null;
                    $row_attrs = 'data-field="' . \esc_attr($child->name) . '"';
                    if ($child->has_conditions()) {
                        $row_attrs .= ' data-conditions="' . \esc_attr($child->get_conditions_json()) . '"';
                    }
                    echo '<div style="margin: 10px 0;" ' . $row_attrs . '>';
                    echo '<label for="' . \esc_attr($child->name) . '" style="display: block; font-weight: 600; margin-bottom: 5px;">' . \esc_html($child->title) . '</label>';
                    $child->render_unbound($value, $child->name, $child->name);
                    echo '</div>';
                }

                echo '</fieldset>';
                echo '</td></tr>';
            } elseif ($field->type === 'advanced' && !empty($field->children)) {
                // Handle advanced fields with children.
                $collapsed = $field->args['collapsed'] ?? true;
                $open_attr = $collapsed ? '' : ' open';

                echo '<tr><td colspan="2">';
                echo '<details style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"' . $open_attr . '>';
                echo '<summary style="cursor: pointer; font-weight: 600; font-size: 14px;">' . \esc_html($field->title) . '</summary>';
                echo '<div style="margin-top: 15px; padding-left: 10px;">';

                if ($field->description) {
                    echo '<p class="description">' . \esc_html($field->description) . '</p>';
                }

                // Render children inside advanced field.
                foreach ($field->children as $child) {
                    $value = $edit_row[$child->name] ?? null;
                    $row_attrs = 'data-field="' . \esc_attr($child->name) . '"';
                    if ($child->has_conditions()) {
                        $row_attrs .= ' data-conditions="' . \esc_attr($child->get_conditions_json()) . '"';
                    }
                    echo '<div style="margin: 10px 0;" ' . $row_attrs . '>';
                    echo '<label for="' . \esc_attr($child->name) . '" style="display: block; font-weight: 600; margin-bottom: 5px;">' . \esc_html($child->title) . '</label>';
                    $child->render_unbound($value, $child->name, $child->name);
                    echo '</div>';
                }

                echo '</div></details>';
                echo '</td></tr>';
            } else {
                // Regular field rendering.
                $value = $edit_row[$field->name] ?? null;
                $row_attrs = 'data-field="' . \esc_attr($field->name) . '"';
                if ($field->has_conditions()) {
                    $row_attrs .= ' data-conditions="' . \esc_attr($field->get_conditions_json()) . '"';
                }
                echo '<tr ' . $row_attrs . '>';
                echo '<th scope="row"><label for="' . \esc_attr($field->name) . '">' . \esc_html($field->title) . '</label></th>';
                echo '<td>';
                $field->render_unbound($value, $field->name, $field->name);
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">' . \esc_html__('Save', 'wp-settings') . '</button></p>';
        echo '</form>';

        if (!empty($rows)) {
            echo '<h3>' . \esc_html__('Delete Item', 'wp-settings') . '</h3>';
            echo '<form method="post">';
            \wp_nonce_field($this->get_nonce_action(), 'wps_table_nonce');
            echo '<input type="hidden" name="wps_table_id" value="' . \esc_attr($this->id) . '">';
            echo '<input type="hidden" name="wps_table_action" value="delete">';
            echo '<select name="row_id">';
            foreach ($rows as $row_id => $row) {
                $label = $row['name'] ?? $row_id;
                echo '<option value="' . \esc_attr($row_id) . '">' . \esc_html($label) . '</option>';
            }
            echo '</select> ';
            echo '<button type="submit" class="button">' . \esc_html__('Delete', 'wp-settings') . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * Render data for JavaScript usage.
     *
     * @param array $rows Rows data.
     */
    protected function render_data_script(array $rows)
    {
        // Build field conditions map for JavaScript.
        $field_conditions = array();
        foreach ($this->fields as $field) {
            if ($field->has_conditions()) {
                $field_conditions[$field->name] = $field->conditions;
            }
        }

        $payload = array(
            'rows' => $rows,
            'status_key' => $this->status_key,
            'statuses' => $this->statuses,
            'field_conditions' => $field_conditions,
        );

        echo '<script type="text/javascript">';
        echo 'window.wpsSettingsTables = window.wpsSettingsTables || {};';
        echo 'window.wpsSettingsTables[' . \wp_json_encode($this->id) . '] = ' . \wp_json_encode($payload) . ';';
        echo '</script>';
    }

    /**
     * Get bulk actions list.
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        if (is_array($this->bulk_actions)) {
            return $this->bulk_actions;
        }

        $actions = array();
        foreach ($this->statuses as $key => $data) {
            $actions[$key] = $data['label'] ?? ucfirst($key);
        }
        $actions['delete'] = __('Delete', 'wp-settings');

        return $actions;
    }

    /**
     * Get option name with text domain prefix.
     *
     * @return string
     */
    protected function get_option_name()
    {
        if ($this->text_domain && \false === strpos($this->option, $this->text_domain)) {
            return $this->text_domain . '_' . $this->option;
        }
        return $this->option;
    }

    /**
     * Retrieve rows from storage.
     *
     * @return array
     */
    protected function get_rows()
    {
        $rows = \get_option($this->get_option_name(), array());
        return is_array($rows) ? $rows : array();
    }

    /**
     * Persist rows.
     *
     * @param array $rows Rows data.
     */
    protected function update_rows(array $rows)
    {
        \update_option($this->get_option_name(), $rows);
    }

    /**
     * Generate a row id.
     *
     * @param array $row Row data.
     * @return string
     */
    protected function generate_row_id(array $row)
    {
        $seed = $row['name'] ?? $row[$this->row_id_key] ?? 'item';
        return \sanitize_title($seed) . '-' . time();
    }

    /**
     * Normalize status value to a status key.
     *
     * @param array $row Row data.
     * @return string
     */
    protected function normalize_status(array $row)
    {
        if (!isset($row[$this->status_key])) {
            return 'disabled';
        }

        $value = $row[$this->status_key];
        if (is_bool($value)) {
            return $value ? 'enabled' : 'disabled';
        }

        return $this->is_valid_status($value) ? $value : 'disabled';
    }

    /**
     * Convert a status key into a stored value.
     *
     * @param string $status Status key.
     * @param array  $row    Row data.
     * @return mixed
     */
    protected function status_value_for($status, array $row)
    {
        $current = $row[$this->status_key] ?? null;

        if (is_bool($current) || $this->status_key === 'enabled') {
            return $status === 'enabled';
        }

        return $status;
    }

    /**
     * Check if a status key is valid.
     *
     * @param string $status Status key.
     * @return bool
     */
    protected function is_valid_status($status)
    {
        return isset($this->statuses[$status]);
    }

    /**
     * Get AJAX action name.
     *
     * @return string
     */
    protected function get_ajax_action()
    {
        return $this->text_domain . '_table_' . $this->id;
    }

    /**
     * Get nonce action.
     *
     * @return string
     */
    protected function get_nonce_action()
    {
        return $this->text_domain . '_table_' . $this->id . '_nonce';
    }

    /**
     * Get nonce value.
     *
     * @return string
     */
    protected function get_nonce()
    {
        return \wp_create_nonce($this->get_nonce_action());
    }

    /**
     * Redirect after non-AJAX action.
     *
     * @param string $status Status flag.
     */
    protected function redirect_with_notice($status)
    {
        $url = \add_query_arg(
            array(
                'wps_table' => $this->id,
                $status => '1',
            ),
            \wp_get_referer()
        );
        \wp_safe_redirect($url);
        exit;
    }

    /**
     * Get notice message from URL.
     *
     * @return string|null
     */
    protected function get_notice_message()
    {
        if (!isset($_GET['wps_table']) || $_GET['wps_table'] !== $this->id) {
            return null;
        }

        if (isset($_GET['saved'])) {
            return __('Saved successfully.', 'wp-settings');
        }
        if (isset($_GET['deleted'])) {
            return __('Deleted successfully.', 'wp-settings');
        }

        return null;
    }
}
