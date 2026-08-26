<?php

declare(strict_types=1);

/**
 * Bootstrap for the Infection process itself.
 *
 * Every file in src/ opens with `if (!defined('ABSPATH')) { die(); }`, and
 * Infection autoloads the classes it mutates in order to reflect on them. In its
 * own process nothing has defined ABSPATH, so the first source file it touches
 * calls die() — the run exits 0 part-way through "Generate mutants", with no
 * mutants, no metrics and nothing on stderr to say why.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/fixtures/');
}
