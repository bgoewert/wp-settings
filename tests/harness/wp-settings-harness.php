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

// Loaded through the wp-content/plugins bind mount, not the repo root: the
// autoloader derives its base directory from its own location, so this is what
// puts __FILE__ under wp-content for every class it registers — and
// plugin_dir_url(__FILE__) is how the library builds its asset URLs.
//
// Skipped when the library is already reachable, which is the integration
// suite: PHPUnit booted this project's autoloader from the repo root, and
// Composer's bootstrap declares a class named after its own hash — so including
// the same one again from a second path is a fatal redeclaration. The test is
// "can this class be autoloaded", not "is any Composer loader present": wp-cli
// ships its own, and that answer would skip the load and leave the class
// missing. Asset URLs are moot in CLI anyway.
$wp_settings_harness_autoload = '/var/www/html/.local/wp/wp-content/plugins/wp-settings-lib/vendor/autoload.php';
if (!class_exists(WP_Settings::class)) {
    if (!file_exists($wp_settings_harness_autoload)) {
        return;
    }
    require_once $wp_settings_harness_autoload;
}

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
            'attendee_columns' => new WP_Setting(
                'attendee_columns',
                'Attendee Columns',
                'dual_list',
                'general',
                'lists',
                null,
                'Move columns into Displayed to show them, and order them there.',
                false,
                array('primary_info', 'ticket'),
                null,
                array(
                    'options' => array(
                        'primary_info' => 'Attendee',
                        'ticket'       => 'Ticket',
                        'email'        => 'Email',
                    ),
                    'available_label' => 'Available',
                    'chosen_label'    => 'Displayed',
                )
            ),
            // A separate field so the awkward key does not disturb the counts the
            // other dual_list tests assert on.
            'quoted_columns' => new WP_Setting(
                'quoted_columns',
                'Quoted Columns',
                'dual_list',
                'general',
                'lists',
                null,
                'An option key holding a double quote (#22).',
                false,
                array(),
                null,
                array(
                    'options' => array(
                        'a"b'   => 'Quoted',
                        'plain' => 'Plain',
                    ),
                )
            ),
            // Drives the conditional field below.
            'provider' => new WP_Setting(
                'provider',
                'Video Provider',
                'select',
                'general',
                'lists',
                '200px',
                'Chooses which provider settings apply.',
                false,
                'none',
                null,
                array(
                    'options' => array(
                        'none'  => 'None',
                        'vimeo' => 'Vimeo',
                    ),
                )
            ),
            // The condition names the field the way it was declared, which is
            // what the README has always shown.
            'provider_note' => new WP_Setting(
                'provider_note',
                'Provider Note',
                'text',
                'general',
                'lists',
                '300px',
                'Only for Vimeo.',
                false,
                null,
                null,
                array(
                    'conditions' => array(
                        array('field' => 'provider', 'operator' => 'equals', 'value' => 'vimeo'),
                    ),
                )
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
