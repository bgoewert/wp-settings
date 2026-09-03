<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings_Table;
use BGoewert\WP_Settings\WP_Settings_Table_Custom_Table_Storage;
use BGoewert\WP_Settings\WP_Settings_Table_Option_Storage;
use BGoewert\WP_Settings\WP_Settings_Table_Storage;

/**
 * Adapter that records what the table asked it to do.
 */
class Recording_Table_Storage implements WP_Settings_Table_Storage
{
    public $rows = array();
    public $calls = array();

    public function get_rows()
    {
        $this->calls[] = 'get_rows';
        return $this->rows;
    }

    public function get_row($row_id)
    {
        $this->calls[] = 'get_row:' . $row_id;
        return $this->rows[$row_id] ?? null;
    }

    public function save_row($row_id, array $row)
    {
        $this->calls[] = 'save_row:' . $row_id;
        $this->rows[$row_id] = $row;
    }

    public function delete_row($row_id)
    {
        $this->calls[] = 'delete_row:' . $row_id;
        unset($this->rows[$row_id]);
    }

    public function set_row_status($row_id, $value)
    {
        $this->calls[] = 'set_row_status:' . $row_id;
        if (isset($this->rows[$row_id])) {
            $this->rows[$row_id]['enabled'] = $value;
        }
    }

    public function replace_rows(array $rows)
    {
        $this->calls[] = 'replace_rows';
        $this->rows = $rows;
    }
}

class Storage_Aware_Table extends WP_Settings_Table
{
    public function test_handle_save(array $data)
    {
        return $this->handle_save($data);
    }

    public function test_handle_delete(array $data)
    {
        $this->handle_delete($data);
    }

    public function test_handle_toggle(array $data)
    {
        $this->handle_toggle($data);
    }

    public function test_handle_toggle_status(array $data)
    {
        $this->handle_toggle_status($data);
    }

    public function test_handle_bulk(array $data)
    {
        $this->handle_bulk($data);
    }

    public function test_get_rows()
    {
        return $this->get_rows();
    }

    public function test_get_storage()
    {
        return $this->get_storage();
    }
}

class WPSettingsTableStorageTest extends WP_Settings_TestCase
{
    private function make_table(array $args = array()): Storage_Aware_Table
    {
        return new Storage_Aware_Table(array_merge(array(
            'id' => 'fees',
            'tab' => 'fees',
            'option' => 'fees',
            'fields' => array(
                new WP_Setting('name', 'Name', 'text', 'fees', 'fees_section'),
                new WP_Setting('enabled', 'Enabled', 'checkbox', 'fees', 'fees_section'),
            ),
        ), $args));
    }

    // Option adapter.

    public function test_option_storage_round_trips_a_row(): void
    {
        $storage = new WP_Settings_Table_Option_Storage('plugin_fees');

        $storage->save_row('fee-1', array('id' => 'fee-1', 'name' => 'Delivery'));

        $this->assertSame(
            array('id' => 'fee-1', 'name' => 'Delivery'),
            $storage->get_row('fee-1')
        );
        $this->assertSame(array('fee-1'), array_keys($this->getOption('plugin_fees')));
    }

    public function test_option_storage_returns_null_for_a_missing_row(): void
    {
        $storage = new WP_Settings_Table_Option_Storage('plugin_fees');

        $this->assertNull($storage->get_row('nope'));
    }

    public function test_option_storage_reads_an_empty_set_from_a_non_array_option(): void
    {
        $this->setOption('plugin_fees', 'not an array');

        $storage = new WP_Settings_Table_Option_Storage('plugin_fees');

        $this->assertSame(array(), $storage->get_rows());
        $this->assertNull($storage->get_row('fee-1'));
    }

    public function test_option_storage_deletes_and_sets_a_status(): void
    {
        $storage = new WP_Settings_Table_Option_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery', 'enabled' => true));
        $storage->save_row('fee-2', array('name' => 'Pickup', 'enabled' => true));

        $storage->set_row_status('fee-1', false);
        $storage->delete_row('fee-2');

        $this->assertFalse($storage->get_row('fee-1')['enabled']);
        $this->assertSame(array('fee-1'), array_keys($storage->get_rows()));
    }

    public function test_option_storage_replaces_the_whole_set(): void
    {
        $storage = new WP_Settings_Table_Option_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery'));

        $storage->replace_rows(array('fee-9' => array('name' => 'Rush')));

        $this->assertSame(array('fee-9'), array_keys($storage->get_rows()));
    }

    // Database table adapter.

    public function test_custom_table_storage_installs_its_schema_once(): void
    {
        global $wp_test_dbdelta_queries;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->get_rows();
        $storage->get_rows();

        $this->assertCount(1, $wp_test_dbdelta_queries);
        $this->assertStringContainsString('CREATE TABLE `wp_plugin_fees`', $wp_test_dbdelta_queries[0]);
        $this->assertStringContainsString('row_id varchar(191) NOT NULL', $wp_test_dbdelta_queries[0]);
        $this->assertStringContainsString('PRIMARY KEY  (row_id)', $wp_test_dbdelta_queries[0]);
        $this->assertStringContainsString('KEY status (status)', $wp_test_dbdelta_queries[0]);
    }

    public function test_custom_table_storage_round_trips_a_row(): void
    {
        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');

        $storage->save_row('fee-1', array('id' => 'fee-1', 'name' => 'Delivery', 'enabled' => true));

        $this->assertSame(
            array('id' => 'fee-1', 'name' => 'Delivery', 'enabled' => true),
            $storage->get_row('fee-1')
        );
        $this->assertSame(array('fee-1'), array_keys($storage->get_rows()));
    }

    public function test_custom_table_storage_saves_a_row_in_one_statement(): void
    {
        global $wpdb;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->get_rows();
        $wpdb->queries = array();

        $storage->save_row('fee-1', array('name' => 'Delivery'));

        $this->assertCount(1, $wpdb->queries);
        $this->assertStringContainsString('INSERT INTO `wp_plugin_fees`', $wpdb->queries[0]);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $wpdb->queries[0]);
    }

    public function test_custom_table_storage_reads_one_row_without_loading_the_rest(): void
    {
        global $wpdb;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery'));
        $storage->save_row('fee-2', array('name' => 'Pickup'));
        $wpdb->queries = array();

        $storage->get_row('fee-1');

        $this->assertCount(1, $wpdb->queries);
        $this->assertStringContainsString('WHERE row_id =', $wpdb->queries[0]);
    }

    public function test_custom_table_storage_keeps_the_creation_time_on_a_resave(): void
    {
        global $wpdb;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery'));

        $wpdb->tables['wp_plugin_fees']['fee-1']['created_at'] = '2020-01-01 00:00:00';
        $storage->save_row('fee-1', array('name' => 'Delivery Renamed'));

        $stored = $wpdb->tables['wp_plugin_fees']['fee-1'];
        $this->assertSame('2020-01-01 00:00:00', $stored['created_at']);
        $this->assertNotSame('2020-01-01 00:00:00', $stored['updated_at']);
        $this->assertSame('Delivery Renamed', $storage->get_row('fee-1')['name']);
    }

    public function test_custom_table_storage_mirrors_the_status_into_its_column(): void
    {
        global $wpdb;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery', 'enabled' => true));

        $this->assertSame('enabled', $wpdb->tables['wp_plugin_fees']['fee-1']['status']);

        $storage->set_row_status('fee-1', false);

        $this->assertSame('disabled', $wpdb->tables['wp_plugin_fees']['fee-1']['status']);
        $this->assertFalse($storage->get_row('fee-1')['enabled']);
    }

    public function test_custom_table_storage_returns_rows_in_creation_order(): void
    {
        global $wpdb;

        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->save_row('zeta', array('name' => 'Zeta'));
        $storage->save_row('alpha', array('name' => 'Alpha'));

        $wpdb->tables['wp_plugin_fees']['zeta']['created_at'] = '2020-01-01 00:00:00';
        $wpdb->tables['wp_plugin_fees']['alpha']['created_at'] = '2020-01-02 00:00:00';

        $this->assertSame(array('zeta', 'alpha'), array_keys($storage->get_rows()));
    }

    public function test_custom_table_storage_deletes_one_row(): void
    {
        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $storage->save_row('fee-1', array('name' => 'Delivery'));
        $storage->save_row('fee-2', array('name' => 'Pickup'));

        $storage->delete_row('fee-1');

        $this->assertNull($storage->get_row('fee-1'));
        $this->assertSame(array('fee-2'), array_keys($storage->get_rows()));
    }

    // Wiring.

    public function test_table_defaults_to_the_option_adapter(): void
    {
        $table = $this->make_table();

        $this->assertInstanceOf(WP_Settings_Table_Option_Storage::class, $table->test_get_storage());
    }

    public function test_table_uses_the_database_adapter_when_asked(): void
    {
        $table = $this->make_table(array('storage' => 'table'));

        $this->assertInstanceOf(WP_Settings_Table_Custom_Table_Storage::class, $table->test_get_storage());
    }

    public function test_setting_the_text_domain_rebuilds_the_tables_own_adapter(): void
    {
        $table = $this->make_table();
        $table->test_get_rows();

        $table->set_text_domain('plugin');
        $table->test_handle_save(array('name' => 'Delivery', 'row_id' => 'fee-1'));

        $this->assertArrayHasKey('fee-1', $this->getOption('plugin_fees'));
        $this->assertFalse($this->getOption('fees'));
    }

    public function test_a_supplied_adapter_survives_the_text_domain(): void
    {
        $storage = new Recording_Table_Storage();
        $table = $this->make_table(array('storage' => $storage));

        $table->set_text_domain('plugin');

        $this->assertSame($storage, $table->test_get_storage());
    }

    // Per-row writes.

    public function test_no_handler_writes_the_whole_row_set(): void
    {
        $storage = new Recording_Table_Storage();
        $table = $this->make_table(array('storage' => $storage));

        $table->test_handle_save(array('row_id' => 'fee-1', 'name' => 'Delivery', 'enabled' => '1'));
        $table->test_handle_save(array('row_id' => 'fee-2', 'name' => 'Pickup', 'enabled' => '1'));
        $table->test_handle_toggle(array('row_id' => 'fee-1'));
        $table->test_handle_toggle_status(array('row_id' => 'fee-1', 'target_status' => 'enabled'));
        $table->test_handle_bulk(array('bulk_action' => 'disabled', 'selected' => array('fee-1')));
        $table->test_handle_bulk(array('bulk_action' => 'delete', 'selected' => array('fee-2')));
        $table->test_handle_delete(array('row_id' => 'fee-1'));

        $this->assertNotContains('replace_rows', $storage->calls);
        $this->assertNotContains('get_rows', $storage->calls);
    }

    public function test_a_bulk_action_leaves_rows_outside_the_selection_alone(): void
    {
        $storage = new Recording_Table_Storage();
        $table = $this->make_table(array('storage' => $storage));

        $table->test_handle_save(array('row_id' => 'fee-1', 'name' => 'Delivery', 'enabled' => '1'));
        $table->test_handle_save(array('row_id' => 'fee-2', 'name' => 'Pickup', 'enabled' => '1'));
        $storage->calls = array();

        $table->test_handle_bulk(array('bulk_action' => 'disabled', 'selected' => array('fee-1')));

        $this->assertSame(
            array('get_row:fee-1', 'set_row_status:fee-1'),
            $storage->calls
        );
        $this->assertTrue($storage->rows['fee-2']['enabled']);
    }

    public function test_two_saves_reading_the_same_empty_table_both_survive(): void
    {
        $storage = new WP_Settings_Table_Custom_Table_Storage('plugin_fees');
        $first = $this->make_table(array('storage' => $storage));
        $second = $this->make_table(array('storage' => $storage));

        // Both read the table before either writes, as two requests would.
        $first->test_get_rows();
        $second->test_get_rows();

        $first->test_handle_save(array('row_id' => 'fee-1', 'name' => 'Delivery', 'enabled' => '1'));
        $second->test_handle_save(array('row_id' => 'fee-2', 'name' => 'Pickup', 'enabled' => '1'));

        $this->assertSame(array('fee-1', 'fee-2'), array_keys($storage->get_rows()));
    }

    // Row ids.

    public function test_a_generated_row_id_is_suffixed_when_it_is_already_stored(): void
    {
        $storage = new Recording_Table_Storage();
        $table = $this->make_table(array('storage' => $storage));

        $first = $table->test_handle_save(array('name' => 'Delivery', 'enabled' => '1'));
        $second = $table->test_handle_save(array('name' => 'Delivery', 'enabled' => '1'));

        $this->assertNotSame($first, $second);
        $this->assertSame($first . '-1', $second);
        $this->assertCount(2, $storage->rows);
    }
}
