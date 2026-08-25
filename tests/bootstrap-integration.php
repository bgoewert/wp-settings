<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the integration suite — real WordPress, no stubs.
 *
 * wp-test-utils looks for a wordpress-tests-lib first and falls back to
 * wp-load.php at the ddev default of /var/www/html/wp-load.php. This repo's
 * docroot is .local/wp (so WordPress core stays out of git), so the path is
 * pointed at the docroot unless the environment already named one.
 */

if (!getenv('WP_TESTS_DIR') && !getenv('WP_LOAD_PATH')) {
    putenv('WP_LOAD_PATH=' . dirname(__DIR__) . '/.local/wp/wp-load.php');
}

require dirname(__DIR__) . '/vendor/seilerinstrument/wp-test-utils/src/Bootstrap/integration.php';

\Seiler\Test\Bootstrap\BootstrapBuilder::create()
    ->after(static function (): void {
        // The harness is an mu-plugin, so WordPress has already constructed it —
        // but register_setting() and add_settings_field() run on admin_init,
        // which never fires under wp-load in CLI. Firing it here is what puts
        // sanitize_option_{$option} in place, and that filter is the whole point
        // of the integration suite: it is where a delimited value is split, and
        // where an array in a string-typed setting used to vanish.
        //
        // add_settings_section() and friends live in wp-admin/includes, which
        // wp-load.php does not pull in — a CLI request is not an admin request.
        require_once ABSPATH . 'wp-admin/includes/admin.php';

        if (!did_action('admin_init')) {
            do_action('admin_init');
        }
    })
    ->run();
