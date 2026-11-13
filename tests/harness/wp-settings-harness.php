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
use BGoewert\WP_Settings\WP_Settings_Table;
use BGoewert\WP_Settings\WP_Settings_Email;

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
        // A fixed log_dir instead of the hashed uploads default, so the e2e suite
        // can seed log files at a path it knows.
        $this->logging(array(
            'plugin_dir_path' => __DIR__,
            'log_dir'         => WP_CONTENT_DIR . '/wp-settings-harness-logs',
        ));

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
            // Shown only for one provider, so the heading and its form-table
            // have to disappear together when another one is chosen (#24).
            'vimeo' => array(
                'name'       => 'Vimeo',
                'tab'        => 'general',
                'tab_name'   => 'General',
                'callback'   => '__return_false',
                'conditions' => array(
                    array('field' => 'provider', 'operator' => 'equals', 'value' => 'vimeo'),
                ),
            ),
            // The tab a settings table renders in; a tab only exists through a section.
            'rows' => array(
                'name'     => 'Rows',
                'tab'      => 'rows',
                'tab_name' => 'Rows',
                'callback' => '__return_false',
            ),
            'mail' => array(
                'name'     => 'Notice',
                'tab'      => 'mail',
                'tab_name' => 'Mail',
                'callback' => '__return_false',
            ),
        );

        $this->tables = array(
            new WP_Settings_Table(array(
                'id'      => 'fees',
                'tab'     => 'rows',
                'title'   => 'Fees',
                'columns' => array(
                    array('key' => 'status', 'label' => 'Status', 'type' => 'status'),
                    array('key' => 'name', 'label' => 'Name', 'field' => 'name'),
                ),
                'fields'  => array(
                    new WP_Setting('name', 'Fee Name', 'text', 'rows', 'rows'),
                    new WP_Setting('enabled', 'Enabled', 'checkbox', 'rows', 'rows'),
                ),
            )),
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
            // Drives both a conditional field and a conditional section.
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
            'vimeo_token' => new WP_Setting(
                'vimeo_token',
                'Vimeo Token',
                'text',
                'general',
                'vimeo',
                '300px',
                'Lives in the conditional section.',
                false,
                null
            ),
            'api_token' => new WP_Setting(
                'api_token',
                'API Token',
                'password',
                'general',
                'lists',
                '300px',
                'Stored ciphered.',
                false,
                null,
                null,
                array('encrypted' => true)
            ),
            'secret_note' => new WP_Setting(
                'secret_note',
                'Secret Note',
                'text',
                'general',
                'lists',
                '300px',
                'Stored ciphered, rendered in the clear.',
                false,
                null,
                null,
                array('encrypted' => true)
            ),
            'secret_block' => new WP_Setting(
                'secret_block',
                'Secret Block',
                'textarea',
                'general',
                'lists',
                '300px',
                'Stored ciphered, rendered in the clear.',
                false,
                null,
                null,
                array('encrypted' => true, 'rows' => 4)
            ),
            'site_logo' => new WP_Setting(
                'site_logo',
                'Site Logo',
                'media',
                'general',
                'lists',
                null,
                'Stored as an attachment id.',
                false,
                null,
                null,
                array('size' => 'thumbnail')
            ),
            'signing_key' => new WP_Setting(
                'signing_key',
                'Signing Key',
                'text',
                'general',
                'lists',
                '300px',
                'Generated, not typed.',
                false,
                null,
                null,
                array('actions' => array(
                    array('label' => 'Generate', 'action' => 'wp_settings_harness_generate'),
                ))
            ),
            'key_status' => new WP_Setting(
                'key_status',
                'Key Status',
                'status',
                'general',
                'lists',
                null,
                'Derived from the signing key.',
                false,
                null,
                null,
                array(
                    'value'   => static fn(): string => '' === (string) get_option(self::TEXT_DOMAIN . '_signing_key', '') ? 'Not set' : 'Set',
                    'actions' => array(
                        array('label' => 'Clear', 'action' => 'wp_settings_harness_clear'),
                        array('render' => static fn(WP_Setting $field): string => sprintf(
                            '<button type="button" class="button" id="wp-settings-harness-check" data-target="%s">Check</button>',
                            esc_attr($field->slug)
                        )),
                    ),
                )
            ),
            'hide_quotes' => new WP_Setting(
                'hide_quotes',
                'Hide Quote Screens',
                'checkbox',
                'general',
                'lists',
                null,
                'Requires the quote plugin to be active.',
                false,
                null,
                null,
                array('disabled' => true)
            ),
            'brand_color' => new WP_Setting(
                'brand_color',
                'Brand Color',
                'color',
                'general',
                'lists',
                null,
                'Suggests the active theme\'s palette.',
                false,
                null,
                null,
                array('palette' => 'theme')
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
            'notice_subject' => new WP_Setting('notice_subject', 'Notice Subject', 'text', 'mail', 'mail', '400px', null, false, self::NOTICE_SUBJECT),
            'notice_body' => new WP_Setting('notice_body', 'Notice Body', 'textarea', 'mail', 'mail', '400px', null, false, self::NOTICE_BODY, null, array('rows' => 6)),
            'notice_preview' => new WP_Setting(
                'notice_preview',
                'Notice Preview',
                'email_preview',
                'mail',
                'mail',
                null,
                'Follows the subject and body as you type.',
                false,
                null,
                null,
                array(
                    'email' => static fn(): WP_Settings_Email => self::notice_email(
                        (string) get_option(self::TEXT_DOMAIN . '_notice_subject', self::NOTICE_SUBJECT),
                        (string) get_option(self::TEXT_DOMAIN . '_notice_body', self::NOTICE_BODY)
                    ),
                    'watch' => array('subject' => 'notice_subject', 'body' => 'notice_body'),
                )
            ),
        );
    }

    public const NOTICE_SUBJECT = '{event_title}: an update';

    public const NOTICE_BODY = "Hi {attendee_name},\n\n{message}";

    /**
     * The notice the Mail tab previews; the e2e suite renders one server-side to compare the repaint with.
     */
    public static function notice_email(string $subject, string $body): WP_Settings_Email
    {
        return new WP_Settings_Email(array(
            'id'      => 'notice',
            'subject' => $subject,
            'body'    => $body,
            'sample'  => array('event_title' => 'Sample Event', 'attendee_name' => 'Jane Doe', 'message' => 'Doors open at 9.'),
        ));
    }
}

/*
 * A site's mail wrapper, chosen by the wp_settings_harness_mail_wrapper option:
 * `brand` is shaped like wp-mu-email's body_html() (escape, link, paragraphs),
 * `drop` replaces the body with fixed markup, anything else leaves mail alone.
 */
add_filter('wp_settings_email', static function ($email) {
    $mode = (string) get_option('wp_settings_harness_mail_wrapper', '');

    if ('drop' === $mode) {
        $email['message'] = '<p>Your event has an update.</p>';
        $email['headers'] = array('Content-Type: text/html; charset=UTF-8');
        return $email;
    }

    if ('brand' !== $mode) {
        return $email;
    }

    $text = htmlspecialchars(str_replace(array("\r\n", "\r"), "\n", $email['message']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = (string) preg_replace_callback('~\bhttps?://[^\s<]+~', static function (array $m): string {
        $url   = $m[0];
        $trail = '';
        while ('' !== $url && false !== strpos('.,!?)', substr($url, -1))) {
            $trail = substr($url, -1) . $trail;
            $url   = substr($url, 0, -1);
        }
        return '<a href="' . $url . '" style="color:#0b4f8a;">' . $url . '</a>' . $trail;
    }, $text);

    $html = '';
    foreach ((array) preg_split('/\n{2,}/', trim($text)) as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ('' !== $paragraph) {
            $html .= '<p style="margin:0 0 16px;">' . str_replace("\n", '<br />', $paragraph) . '</p>';
        }
    }

    $email['message'] = '<!DOCTYPE html><html><body style="font-family:&quot;Inter&quot;,sans-serif;"><table role="presentation"><tr><td>' . $html . '</td></tr></table></body></html>';
    $email['subject'] = '[Harness] ' . $email['subject'];
    $email['headers'] = array('Content-Type: text/html; charset=UTF-8');

    return $email;
}, 20);

// The consumer's half of a field action: the library has already checked the
// nonce and capability, and redirects back once this returns.
add_action('admin_post_wp_settings_harness_generate', static function (): void {
    update_option(WP_Settings_Harness::TEXT_DOMAIN . '_signing_key', wp_generate_password(32, false));
});

add_action('admin_post_wp_settings_harness_clear', static function (): void {
    update_option(WP_Settings_Harness::TEXT_DOMAIN . '_signing_key', '');
});

// The consumer's half of a rendered action: its own script answers in place.
add_action('admin_footer', static function (): void {
    echo "<script>document.getElementById('wp-settings-harness-check')?.addEventListener('click', (e) => {"
        . "document.getElementById(e.currentTarget.dataset.target).textContent = 'Checked';"
        . "});</script>";
});

// Exposed so the integration suite can render a field without re-declaring it.
$GLOBALS['wp_settings_harness'] = new WP_Settings_Harness();
