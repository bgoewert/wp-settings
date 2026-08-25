<?php

/**
 * Plugin Name: WP Settings Harness
 * Description: Registers the settings page the integration and e2e suites drive. Loaded as an mu-plugin by .ddev/commands/web/wp-bootstrap.
 * Author: Seiler Instrument
 *
 * @package BGoewert\WP_Settings
 */

declare(strict_types=1);

use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings;

if (!defined('ABSPATH')) {
    exit;
}

// The library is the repo, mounted at the ddev project root rather than
// installed into wp-content — so the autoloader is required by path.
$wp_settings_harness_autoload = '/var/www/html/vendor/autoload.php';
if (!file_exists($wp_settings_harness_autoload)) {
    return;
}
require_once $wp_settings_harness_autoload;

/**
 * A settings page carrying one delimited text field, one delimited textarea and
 * one plain text field.
 *
 * The plain field is the control: it is what proves a `delimiter` field's array
 * storage is opt-in rather than the new default.
 */
final class WP_Settings_Harness extends WP_Settings
{
    public const TEXT_DOMAIN = 'wp_settings_harness';

    public function __construct()
    {
        // Parent first: it is what sets WP_Setting::$text_domain, and each
        // WP_Setting fixes its option slug from that static at construction —
        // build the fields before this call and they register unprefixed keys.
        parent::__construct(self::TEXT_DOMAIN);

        $this->sections = array(
            'lists' => array(
                'name'     => 'Delimited Lists',
                'tab'      => 'general',
                'tab_name' => 'General',
                'callback' => '__return_false',
            ),
        );

        $this->settings = array(
            'rental_tags' => new WP_Setting(
                'rental_tags',
                'Rental Tags',
                'text',
                'general',
                'lists',
                '300px',
                'Comma-separated.',
                false,
                'rental, demo',
                null,
                array('delimiter' => ',', 'reset_button' => true)
            ),
            'allowed_domains' => new WP_Setting(
                'allowed_domains',
                'Allowed Domains',
                'textarea',
                'general',
                'lists',
                '300px',
                'One per line.',
                false,
                null,
                null,
                array('delimiter' => "\n", 'rows' => 4)
            ),
            'plain_note' => new WP_Setting(
                'plain_note',
                'Plain Note',
                'text',
                'general',
                'lists',
                '300px',
                'No delimiter — stays a string.',
                false,
                null
            ),
        );
    }
}

// Exposed so the integration suite can render a field without re-declaring it.
$GLOBALS['wp_settings_harness'] = new WP_Settings_Harness();
