<?php

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Settings_Logger;

class Test_WP_Settings_With_Logging extends WP_Settings
{
    public function __construct(string $plugin_dir_path)
    {
        $this->sections = array(
            'general_settings' => array(
                'name' => 'General Settings',
                'tab' => 'general',
                'callback' => '__return_false',
            ),
        );

        $this->settings = array(
            'sample_field' => new WP_Setting('sample_field', 'Sample Field', 'text', 'general', 'general_settings'),
        );

        $this->logging(array(
            'plugin_dir_path' => $plugin_dir_path,
        ));

        parent::__construct('my-plugin');
    }
}

class WPSettingsLoggerTest extends WP_Settings_TestCase
{
    private $plugin_dir_path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin_dir_path = sys_get_temp_dir() . '/wp-settings-logger-' . uniqid('', true);
        mkdir($this->plugin_dir_path, 0777, true);
        $_GET = array();
        $_POST = array();
    }

    protected function tearDown(): void
    {
        $_GET = array();
        $_POST = array();
        $this->delete_directory($this->plugin_dir_path);
        parent::tearDown();
    }

    public function test_logger_writes_plugin_log_file(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'info');

        $logger = $settings->get_logger();

        $this->assertInstanceOf(WP_Settings_Logger::class, $logger);

        $logger->info('Settings saved', array('tab' => 'general'));

        $log_file = $this->plugin_dir_path . '/logs/my_plugin-' . date('Y-m-d') . '.log';

        $this->assertFileExists($log_file);
        $this->assertStringContainsString('Settings saved', (string) file_get_contents($log_file));
        $this->assertStringContainsString('general', (string) file_get_contents($log_file));
    }

    public function test_logger_rotates_old_plugin_logs(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        mkdir($this->plugin_dir_path . '/logs', 0777, true);
        $old_file = $this->plugin_dir_path . '/logs/my_plugin-2000-01-01.log';
        file_put_contents($old_file, 'old log');
        touch($old_file, strtotime('-10 days'));

        $this->setOption('my_plugin_log_retention_days', 1);

        $logger->rotate_logs();

        $this->assertFileDoesNotExist($old_file);
    }

    public function test_logging_tab_fields_are_registered(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        // Simulate admin_init priority 0 (deferred logging append) firing before init().
        $settings->_append_logging_definitions_once();
        $settings->init();

        $fields = $this->getRegisteredSettingsFields();
        $sections = $this->getRegisteredSettingsSections();

        $this->assertArrayHasKey('my_plugin_logging_enabled_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_destination_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_level_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_retention_days_field', $fields);
        $this->assertArrayHasKey('my_plugin_log_auto_refresh_field', $fields);
        $this->assertArrayHasKey('my_plugin_section_logging_settings', $sections);
    }

    public function test_logging_tab_renders_viewer(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'error');

        $logger->error('Viewer entry');

        $_GET['tab'] = 'logging';

        // Simulate admin_init priority 0 firing before menu_page_callback().
        $settings->_append_logging_definitions_once();

        ob_start();
        $settings->menu_page_callback();
        $output = ob_get_clean();

        $this->assertStringContainsString('Log Viewer', $output);
        $this->assertStringContainsString('Viewer entry', $output);
        $this->assertStringContainsString('wps-log-refresh', $output);
    }

    public function test_ajax_clear_log_removes_plugin_logs(): void
    {
        $settings = new Test_WP_Settings_With_Logging($this->plugin_dir_path);
        $logger = $settings->get_logger();

        $this->setOption('my_plugin_logging_enabled', 'on');
        $this->setOption('my_plugin_log_destination', 'plugin');
        $this->setOption('my_plugin_log_level', 'error');

        $logger->error('Clear me');

        $_POST['nonce'] = 'nonce-my_plugin_logging_clear';

        $result = $settings->ajax_clear_log();

        $this->assertTrue($result['success']);
        $this->assertSame(array(), $logger->get_log_files());
    }

    private function delete_directory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($item_path)) {
                $this->delete_directory($item_path);
            } elseif (file_exists($item_path)) {
                unlink($item_path);
            }
        }

        rmdir($path);
    }
}
