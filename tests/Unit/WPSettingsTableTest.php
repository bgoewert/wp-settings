<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings_Table;

class Test_WP_Settings_Table extends WP_Settings_Table
{
    public function test_handle_save(array $data)
    {
        return $this->handle_save($data);
    }

    public function test_handle_toggle(array $data)
    {
        $this->handle_toggle($data);
    }

    public function test_handle_bulk(array $data)
    {
        $this->handle_bulk($data);
    }

    public function test_handle_delete(array $data)
    {
        $this->handle_delete($data);
    }

    public function test_render_table_form(array $rows): string
    {
        ob_start();
        $this->render_table_form($rows, 'my-plugin', 'fees');
        return ob_get_clean();
    }
}

class WPSettingsTableTest extends WP_Settings_TestCase
{
    /**
     * The visible text of the label bound to each checkbox, keyed by the checkbox's name or class.
     *
     * @return array<int, array{control: string, label: ?string}>
     */
    private function checkbox_names(string $html): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new DOMXPath($doc);

        $names = array();
        foreach ($xpath->query('//input[@type="checkbox"]') as $input) {
            $id = $input->getAttribute('id');
            $labels = $id === '' ? null : $xpath->query('//label[@for="' . $id . '"]');
            $names[] = array(
                'control' => $input->getAttribute('name') ?: $input->getAttribute('class'),
                'label' => $labels && $labels->length === 1 ? trim($labels->item(0)->textContent) : null,
            );
        }

        return $names;
    }

    private function make_table(string $id = 'fees'): Test_WP_Settings_Table
    {
        $fields = array(
            new WP_Setting('name', 'Name', 'text', 'fees', 'fees_section'),
            new WP_Setting('amount', 'Amount', 'number', 'fees', 'fees_section'),
            new WP_Setting('enabled', 'Enabled', 'checkbox', 'fees', 'fees_section'),
        );

        $table = new Test_WP_Settings_Table(
            array(
                'id' => $id,
                'tab' => 'fees',
                'option' => 'fees',
                'title' => 'Fees',
                'fields' => $fields,
                'columns' => array(
                    array('key' => 'status', 'label' => 'Status', 'type' => 'status'),
                    array('key' => 'name', 'label' => 'Name', 'field' => 'name'),
                    array('key' => 'amount', 'label' => 'Amount', 'field' => 'amount'),
                ),
            )
        );
        $table->set_text_domain('my-plugin');

        return $table;
    }

    public function test_handle_save_persists_row(): void
    {
        $table = $this->make_table();

        $row_id = $table->test_handle_save(
            array(
                'row_id' => 'fee-1',
                'name' => 'Test Fee',
                'amount' => '5',
                'enabled' => 'on',
            )
        );

        $this->assertSame('fee-1', $row_id);

        $rows = $this->getOption('my-plugin_fees', array());
        $this->assertArrayHasKey('fee-1', $rows);
        $this->assertSame('fee-1', $rows['fee-1']['id']);
        $this->assertSame('Test Fee', $rows['fee-1']['name']);
        $this->assertSame('5', $rows['fee-1']['amount']);
        $this->assertTrue($rows['fee-1']['enabled']);
    }

    public function test_handle_toggle_updates_status(): void
    {
        $table = $this->make_table();

        $this->setOption(
            'my-plugin_fees',
            array(
                'fee-1' => array(
                    'id' => 'fee-1',
                    'name' => 'Test Fee',
                    'amount' => '5',
                    'enabled' => true,
                ),
            )
        );

        $table->test_handle_toggle(array('row_id' => 'fee-1'));

        $rows = $this->getOption('my-plugin_fees', array());
        $this->assertFalse($rows['fee-1']['enabled']);
    }

    public function test_handle_bulk_updates_status(): void
    {
        $table = $this->make_table();

        $this->setOption(
            'my-plugin_fees',
            array(
                'fee-1' => array('id' => 'fee-1', 'enabled' => true),
                'fee-2' => array('id' => 'fee-2', 'enabled' => true),
            )
        );

        $table->test_handle_bulk(
            array(
                'bulk_action' => 'disabled',
                'selected' => array('fee-1', 'fee-2'),
            )
        );

        $rows = $this->getOption('my-plugin_fees', array());
        $this->assertFalse($rows['fee-1']['enabled']);
        $this->assertFalse($rows['fee-2']['enabled']);
    }

    public function test_handle_bulk_delete_removes_rows(): void
    {
        $table = $this->make_table();

        $this->setOption(
            'my-plugin_fees',
            array(
                'fee-1' => array('id' => 'fee-1'),
                'fee-2' => array('id' => 'fee-2'),
            )
        );

        $table->test_handle_bulk(
            array(
                'bulk_action' => 'delete',
                'selected' => array('fee-1', 'fee-2'),
            )
        );

        $rows = $this->getOption('my-plugin_fees', array());
        $this->assertSame(array(), $rows);
    }

    public function test_select_checkboxes_are_named_by_their_row(): void
    {
        $html = $this->make_table()->test_render_table_form(
            array(
                'fee-1' => array('id' => 'fee-1', 'name' => 'Rush Fee', 'enabled' => true),
            )
        );

        $this->assertSame(
            array(
                array('control' => 'wps-select-all', 'label' => 'Select all'),
                array('control' => 'selected[]', 'label' => 'Select Rush Fee'),
            ),
            $this->checkbox_names($html)
        );
    }

    public function test_select_checkbox_falls_back_to_the_row_id_when_the_row_has_no_name(): void
    {
        $html = $this->make_table()->test_render_table_form(
            array('fee-1' => array('id' => 'fee-1', 'name' => '', 'amount' => '')),
        );

        $this->assertSame('Select fee-1', $this->checkbox_names($html)[1]['label']);
    }

    public function test_two_tables_on_one_page_keep_their_labels_apart(): void
    {
        $rows = array('fee-1' => array('id' => 'fee-1', 'name' => 'Rush Fee'));
        $other = array('fee-1' => array('id' => 'fee-1', 'name' => 'Late Fee'));

        $html = $this->make_table('fees')->test_render_table_form($rows)
            . $this->make_table('discounts')->test_render_table_form($other);

        $labels = array_column($this->checkbox_names($html), 'label');
        $this->assertSame(array('Select all', 'Select Rush Fee', 'Select all', 'Select Late Fee'), $labels);
    }
}
