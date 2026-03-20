<?php

namespace BGoewert\WP_Settings;

if (!defined('ABSPATH')) {
    die();
}

if (class_exists('BGoewert\\WP_Settings\\WP_Settings_Logger')) {
    return;
}

class WP_Settings_Logger
{
    protected $plugin_dir_path;
    protected $text_domain;
    protected $default_level;

    public function __construct(array $args)
    {
        $this->plugin_dir_path = isset($args['plugin_dir_path'])
            ? rtrim((string) $args['plugin_dir_path'], '/\\')
            : '';
        $this->text_domain = isset($args['text_domain'])
            ? WP_Setting::normalize_text_domain((string) $args['text_domain'])
            : 'wp_settings';
        $this->default_level = isset($args['default_level'])
            ? $this->normalize_level((string) $args['default_level'])
            : 'error';
    }

    public function is_enabled(): bool
    {
        return $this->get_bool_setting('logging_enabled', false);
    }

    public function get_destination(): string
    {
        $destination = (string) $this->get_setting('log_destination', 'plugin');

        return in_array($destination, array('plugin', 'wordpress'), true)
            ? $destination
            : 'plugin';
    }

    public function get_level(): string
    {
        return $this->normalize_level((string) $this->get_setting('log_level', $this->default_level));
    }

    public function get_retention_days(): int
    {
        $days = (int) $this->get_setting('log_retention_days', 14);

        return $days > 0 ? $days : 14;
    }

    public function get_auto_refresh_interval(): int
    {
        $interval = (int) $this->get_setting('log_auto_refresh', 0);

        return $interval >= 0 ? $interval : 0;
    }

    public function get_log_dir(): string
    {
        if ($this->plugin_dir_path === '') {
            return '';
        }

        return $this->plugin_dir_path . DIRECTORY_SEPARATOR . 'logs';
    }

    public function get_log_file($date = null): string
    {
        $date = $date ?: date('Y-m-d');

        return $this->get_log_dir() . DIRECTORY_SEPARATOR . $this->text_domain . '-' . $date . '.log';
    }

    public function get_log_files(): array
    {
        $dir = $this->get_log_dir();

        if ($dir === '' || !is_dir($dir)) {
            return array();
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . $this->text_domain . '-*.log');

        if ($files === false) {
            return array();
        }

        usort($files, static function ($left, $right) {
            return strcmp(basename((string) $right), basename((string) $left));
        });

        return array_map('basename', $files);
    }

    public function get_log_size($file = ''): int
    {
        $path = $this->resolve_log_path($file);

        return $path !== '' && file_exists($path) ? (int) filesize($path) : 0;
    }

    public function log(string $level, string $message, array $context = array()): void
    {
        $level = $this->normalize_level($level);

        if (!$this->should_log($level)) {
            return;
        }

        $entry = $this->format_entry($level, $message, $context);

        if ($this->get_destination() === 'wordpress') {
            error_log('[' . $this->text_domain . '] ' . trim($entry));
            return;
        }

        $dir = $this->get_log_dir();
        if ($dir === '') {
            return;
        }

        $this->ensure_log_dir();
        $this->rotate_logs();
        file_put_contents($this->get_log_file(), $entry, FILE_APPEND);
    }

    public function debug(string $message, array $context = array()): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = array()): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = array()): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = array()): void
    {
        $this->log('error', $message, $context);
    }

    public function get_log_contents(string $file = '', int $lines = 200): string
    {
        $path = $this->resolve_log_path($file);

        if ($path === '' || !file_exists($path)) {
            return '';
        }

        if ($lines > 0) {
            $contents = file($path, FILE_IGNORE_NEW_LINES);
            if ($contents === false) {
                return '';
            }

            return implode(PHP_EOL, array_slice($contents, -$lines));
        }

        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    public function get_log_tail(string $file = '', int $offset = 0): array
    {
        $path = $this->resolve_log_path($file);

        if ($path === '' || !file_exists($path)) {
            return array(
                'content' => '',
                'new_offset' => 0,
                'file_size' => 0,
                'file' => '',
            );
        }

        $file_size = (int) filesize($path);

        if ($offset > $file_size) {
            $offset = 0;
        }

        $bytes_to_read = $file_size - $offset;

        if ($bytes_to_read <= 0) {
            return array(
                'content' => '',
                'new_offset' => $offset,
                'file_size' => $file_size,
                'file' => basename($path),
            );
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return array(
                'content' => '',
                'new_offset' => $offset,
                'file_size' => $file_size,
                'file' => basename($path),
            );
        }

        fseek($handle, $offset);
        $content = fread($handle, $bytes_to_read);
        fclose($handle);

        return array(
            'content' => $content === false ? '' : $content,
            'new_offset' => $file_size,
            'file_size' => $file_size,
            'file' => basename($path),
        );
    }

    public function clear_logs(): bool
    {
        $files = $this->get_log_files();
        $cleared = true;

        foreach ($files as $file) {
            $path = $this->resolve_log_path($file);
            if ($path !== '' && file_exists($path) && !unlink($path)) {
                $cleared = false;
            }
        }

        return $cleared;
    }

    public function rotate_logs(): void
    {
        $retention_days = $this->get_retention_days();
        $files = $this->get_log_files();

        if ($retention_days <= 0 || empty($files)) {
            return;
        }

        $threshold = strtotime('-' . $retention_days . ' days');
        if ($threshold === false) {
            return;
        }

        foreach ($files as $file) {
            $path = $this->resolve_log_path($file);
            if ($path === '' || !file_exists($path)) {
                continue;
            }

            $modified = filemtime($path);
            if ($modified !== false && $modified < $threshold) {
                unlink($path);
            }
        }
    }

    protected function should_log(string $level): bool
    {
        if (!$this->is_enabled()) {
            return false;
        }

        return $this->get_level_weight($level) >= $this->get_level_weight($this->get_level());
    }

    protected function get_setting(string $name, $default = null)
    {
        return WP_Setting::get($name, $default);
    }

    protected function get_bool_setting(string $name, bool $default): bool
    {
        $value = $this->get_setting($name, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array((string) $value, array('1', 'true', 'yes', 'on'), true);
    }

    protected function normalize_level(string $level): string
    {
        $level = strtolower($level);

        return in_array($level, array('debug', 'info', 'warning', 'error'), true)
            ? $level
            : 'error';
    }

    protected function get_level_weight(string $level): int
    {
        $weights = array(
            'debug' => 100,
            'info' => 200,
            'warning' => 300,
            'error' => 400,
        );

        return $weights[$this->normalize_level($level)] ?? 400;
    }

    protected function ensure_log_dir(): void
    {
        $dir = $this->get_log_dir();

        if ($dir === '' || is_dir($dir)) {
            return;
        }

        mkdir($dir, 0777, true);
    }

    protected function format_entry(string $level, string $message, array $context = array()): string
    {
        $entry = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message;

        if (!empty($context)) {
            $json = json_encode($context);
            if ($json !== false) {
                $entry .= ' | ' . $json;
            }
        }

        return $entry . PHP_EOL;
    }

    protected function resolve_log_path(string $file = ''): string
    {
        if ($file === '') {
            return $this->get_log_file();
        }

        $sanitized = basename($file);
        if ($sanitized === '') {
            return '';
        }

        return $this->get_log_dir() . DIRECTORY_SEPARATOR . $sanitized;
    }
}
