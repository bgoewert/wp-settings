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

    /** Consumer-supplied absolute path; empty means derive it from the uploads directory. */
    protected $log_dir;

    /** Set once per request so a broken log directory is reported once, not per entry. */
    protected $write_failure_reported = false;

    /** Set once per request so the legacy directory is only scanned once. */
    protected $legacy_migration_attempted = false;

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
        $this->log_dir = isset($args['log_dir'])
            ? rtrim((string) $args['log_dir'], '/\\')
            : '';
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

    /**
     * Directory that log files are written to and read from.
     *
     * Defaults to an uploads subdirectory carrying a `wp_hash()` suffix, and falls back to
     * the plugin directory only where uploads are unusable. Neither location is reliably
     * outside the web root, so it is the per-site suffix — not the folder — that stops an
     * unauthenticated visitor fetching a log file by guessing the text domain and a date
     * (#15). Rotating the site's salts changes the suffix and orphans older files; the
     * retention setting prunes them.
     */
    public function get_log_dir(): string
    {
        if ($this->log_dir !== '') {
            return $this->log_dir;
        }

        $uploads = \wp_upload_dir(null, false);
        $basedir = isset($uploads['basedir']) ? rtrim((string) $uploads['basedir'], '/\\') : '';

        if ($basedir === '' || !empty($uploads['error'])) {
            return $this->get_legacy_log_dir();
        }

        // Resolved per call rather than cached: on multisite the uploads base moves with
        // switch_to_blog(), and core already caches the lookup for the request.
        return $basedir . DIRECTORY_SEPARATOR . $this->get_log_dir_name();
    }

    public function get_log_file($date = null): string
    {
        $date = $date ?: date('Y-m-d');

        return $this->get_log_dir() . DIRECTORY_SEPARATOR . $this->text_domain . '-' . $date . '.log';
    }

    public function get_log_files(): array
    {
        // Listing is the first thing the viewer does after an upgrade, so migrate here too
        // rather than making the admin wait for the next write to see their own history.
        if (!$this->legacy_migration_attempted && $this->has_legacy_logs()) {
            $this->ensure_log_dir();
        }

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
        } elseif ($this->ensure_log_dir()) {
            $this->rotate_logs();
            $this->write_entry($entry);
        }

        $this->maybe_send_notification($level, $message, $entry);
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

    public function get_notify_emails(): array
    {
        $raw = (string) $this->get_setting('log_notify_emails', '');
        if ($raw === '') {
            return array();
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    protected function maybe_send_notification(string $level, string $message, string $entry): void
    {
        if ($level !== 'error') {
            return;
        }

        if (!$this->get_bool_setting('log_notify_errors', false)) {
            return;
        }

        $emails = $this->get_notify_emails();
        if (empty($emails)) {
            return;
        }

        $subject = sprintf('[%s] Error Alert — %s', $this->text_domain, substr(strip_tags($message), 0, 100));
        $body    = trim($entry);

        foreach ($emails as $email) {
            \wp_mail($email, $subject, $body);
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

    /**
     * Create the log directory, guard it, and return whether it can be written to.
     *
     * `wp_mkdir_p()` honours `FS_CHMOD_DIR` (0755 by default) where the old `mkdir(0777)`
     * left a world-writable directory. Returning a bool lets `log()` stop instead of
     * appending to a path that does not exist, once per entry, with the warning swallowed.
     * The first failure turns file logging off for the rest of the request, so a broken
     * directory costs one `error_log()` line and one `mkdir()` attempt, not one per entry.
     */
    protected function ensure_log_dir(): bool
    {
        $dir = $this->get_log_dir();

        if ($dir === '' || $this->write_failure_reported) {
            return false;
        }

        if (!is_dir($dir) && !\wp_mkdir_p($dir)) {
            $this->report_write_failure('cannot create log directory ' . $dir);

            return false;
        }

        $this->protect_dir($dir);
        $this->migrate_legacy_logs();

        return true;
    }

    /**
     * Write the deny guards, backfilling directories that predate them.
     *
     * `.htaccess` only covers Apache, so on nginx the unguessable directory name is the
     * whole of the protection.
     */
    protected function protect_dir(string $dir): void
    {
        $guards = array(
            'index.php' => "<?php\n// Silence is golden.\n",
            '.htaccess' => "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
        );

        foreach ($guards as $name => $contents) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;

            if (!file_exists($path)) {
                file_put_contents($path, $contents);
            }
        }
    }

    /**
     * Move logs written to `<plugin-dir>/logs` by earlier versions into the current dir.
     *
     * Without this every install that ever logged keeps a readable copy at the old
     * predictable URL, so the fix would only protect entries written from now on.
     */
    protected function migrate_legacy_logs(): void
    {
        if ($this->legacy_migration_attempted) {
            return;
        }

        $this->legacy_migration_attempted = true;

        $legacy = $this->get_legacy_log_dir();
        $dir = $this->get_log_dir();

        if ($legacy === '' || $legacy === $dir || !is_dir($legacy)) {
            return;
        }

        $files = glob($legacy . DIRECTORY_SEPARATOR . $this->text_domain . '-*.log');

        foreach ($files === false ? array() : $files as $file) {
            $target = $dir . DIRECTORY_SEPARATOR . basename((string) $file);

            if (!file_exists($target)) {
                $this->move_file((string) $file, $target);
            }
        }

        $entries = scandir($legacy);
        $leftovers = $entries === false
            ? array('unknown')
            : array_diff($entries, array('.', '..', 'index.php', '.htaccess'));

        // Anything the move could not claim stays put and gets guarded instead; a directory
        // holding nothing but this class's own guards is removed rather than left behind.
        if (!empty($leftovers)) {
            $this->protect_dir($legacy);

            return;
        }

        foreach (array('index.php', '.htaccess') as $guard) {
            $path = $legacy . DIRECTORY_SEPARATOR . $guard;

            if (file_exists($path)) {
                unlink($path);
            }
        }

        @rmdir($legacy);
    }

    /**
     * Move one file, copying where a plain rename cannot cross the boundary.
     *
     * Hosts that mount uploads separately from the plugin directory fail `rename()` with
     * EXDEV, which would leave the exposed copy behind — the thing the move exists to remove.
     */
    protected function move_file(string $source, string $target): bool
    {
        if (@rename($source, $target)) {
            return true;
        }

        if (!copy($source, $target)) {
            return false;
        }

        return unlink($source);
    }

    protected function has_legacy_logs(): bool
    {
        $legacy = $this->get_legacy_log_dir();

        if ($legacy === '' || $legacy === $this->get_log_dir() || !is_dir($legacy)) {
            return false;
        }

        $files = glob($legacy . DIRECTORY_SEPARATOR . $this->text_domain . '-*.log');

        return !empty($files);
    }

    protected function get_legacy_log_dir(): string
    {
        if ($this->plugin_dir_path === '') {
            return '';
        }

        return $this->plugin_dir_path . DIRECTORY_SEPARATOR . 'logs';
    }

    protected function get_log_dir_name(): string
    {
        $suffix = substr(\wp_hash($this->text_domain . '|wp-settings-logs'), 0, 16);

        return $this->text_domain . '-logs-' . $suffix;
    }

    protected function write_entry(string $entry): void
    {
        $file = $this->get_log_file();

        if (file_put_contents($file, $entry, FILE_APPEND) === false) {
            $this->report_write_failure('cannot write ' . $file);
        }
    }

    protected function report_write_failure(string $reason): void
    {
        if ($this->write_failure_reported) {
            return;
        }

        $this->write_failure_reported = true;

        error_log('[' . $this->text_domain . '] file logging unavailable: ' . $reason);
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
