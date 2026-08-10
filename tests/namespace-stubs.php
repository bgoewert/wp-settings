<?php

/**
 * Namespace-scoped function stubs.
 *
 * The library calls extension_loaded() unqualified from inside
 * BGoewert\WP_Settings, so PHP resolves it to this stub before the global
 * function. That lets the suite simulate a PHP build without sodium (or without
 * openssl) on a host where both are compiled in — see issue #12, which only
 * reproduces on sodium-less runtimes such as WordPress Playground's WASM build.
 *
 * This file must be loaded before the library's classes are used.
 */

namespace BGoewert\WP_Settings;

function extension_loaded(string $extension): bool
{
    global $wp_test_disabled_extensions;

    if (in_array($extension, (array) $wp_test_disabled_extensions, true)) {
        return false;
    }

    return \extension_loaded($extension);
}
