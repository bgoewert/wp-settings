<?php

use BGoewert\WP_Settings\WP_Settings_Email;

/**
 * An email composed from a template and sent, or previewed, through the site's mail filter.
 */
class WPSettingsEmailTest extends WP_Settings_TestCase
{
    private function email(array $args = array()): WP_Settings_Email
    {
        return new WP_Settings_Email($args + array(
            'id'      => 'attendee_message',
            'subject' => '{event_title}: an update',
            'body'    => "Hi {attendee_name},\n\n{message}",
            'sample'  => array('event_title' => 'Sample Event', 'attendee_name' => 'Jane Doe', 'message' => 'Doors open at 9.'),
        ));
    }

    /**
     * A wrapper shaped like wp-mu-email's: escape, paragraphs, <br /> for single newlines, styled links.
     */
    public static function branded(array $email): array
    {
        $text = htmlspecialchars(str_replace(array("\r\n", "\r"), "\n", $email['message']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = (string) preg_replace_callback('~\bhttps?://[^\s<]+~', static function (array $m): string {
            $url = $m[0];
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

        $email['message'] = '<!DOCTYPE html><html><body style="font-family:&quot;Inter&quot;,sans-serif;"><table><tr><td>' . $html . '</td></tr></table></body></html>';
        $email['subject'] = '[Seiler] ' . $email['subject'];
        $email['headers'] = array('Content-Type: text/html; charset=UTF-8');

        return $email;
    }

    private function brand(): void
    {
        add_filter('wp_settings_email', array(self::class, 'branded'), 20);
    }

    public function test_placeholders_fill_from_the_context(): void
    {
        $email = $this->email()->render(array('event_title' => 'Fall Expo', 'attendee_name' => 'Sam', 'message' => 'See you there.'));

        $this->assertSame('Fall Expo: an update', $email['subject']);
        $this->assertSame("Hi Sam,\n\nSee you there.", $email['message']);
    }

    public function test_a_token_with_no_value_is_left_as_written(): void
    {
        $email = $this->email()->render(array('event_title' => 'Fall Expo'));

        $this->assertSame("Hi {attendee_name},\n\n{message}", $email['message']);
    }

    public function test_context_values_are_substituted_as_plain_text(): void
    {
        $email = $this->email()->render(array('attendee_name' => '<b>Sam</b> & co', 'message' => '{event_title}'));

        $this->assertSame("Hi <b>Sam</b> & co,\n\n{event_title}", $email['message'], 'no escaping, and a value is not itself expanded');
    }

    public function test_render_defaults_to_the_sample(): void
    {
        $this->assertSame('Sample Event: an update', $this->email()->render()['subject']);
    }

    public function test_the_filter_receives_core_shape_the_email_and_its_context(): void
    {
        $seen = null;
        add_filter('wp_settings_email', static function ($email, $object, $context) use (&$seen) {
            $seen = array($email, $object, $context);
            return $email;
        }, 10, 3);

        $subject = $this->email(array('headers' => array('Reply-To: events@example.com')));
        $subject->render(array('event_title' => 'Fall Expo'), 'jane@example.com');

        $this->assertSame(array('to', 'subject', 'message', 'headers'), array_keys($seen[0]));
        $this->assertSame('jane@example.com', $seen[0]['to']);
        $this->assertSame(array('Reply-To: events@example.com'), $seen[0]['headers']);
        $this->assertSame($subject, $seen[1]);
        $this->assertSame(array('event_title' => 'Fall Expo'), $seen[2]);
    }

    public function test_a_site_wrapper_replaces_the_message_and_headers(): void
    {
        $this->brand();

        $email = $this->email()->render();

        $this->assertStringContainsString('<p style="margin:0 0 16px;">Hi Jane Doe,</p>', $email['message']);
        $this->assertSame('[Seiler] Sample Event: an update', $email['subject']);
        $this->assertSame(array('Content-Type: text/html; charset=UTF-8'), $email['headers']);
    }

    public function test_a_filter_returning_null_falls_back_to_the_unfiltered_email(): void
    {
        add_filter('wp_settings_email', static fn() => null);

        $email = $this->email()->render();

        $this->assertSame('Sample Event: an update', $email['subject']);
        $this->assertSame("Hi Jane Doe,\n\nDoors open at 9.", $email['message']);
    }

    public function test_a_filter_returning_an_array_without_a_string_message_falls_back(): void
    {
        add_filter('wp_settings_email', static fn($email) => array('subject' => 'Hijacked', 'message' => array('x')));

        $this->assertSame('Sample Event: an update', $this->email()->render()['subject']);
    }

    public function test_a_filter_returning_an_array_without_a_message_falls_back(): void
    {
        add_filter('wp_settings_email', static fn($email) => array('subject' => 'Hijacked'));

        $this->assertSame('Sample Event: an update', $this->email()->render()['subject']);
    }

    public function test_keys_a_filter_adds_are_dropped_and_keys_it_omits_are_kept(): void
    {
        add_filter('wp_settings_email', static fn($email) => array('message' => 'Wrapped', 'attachments' => array('/etc/passwd')));

        $this->assertSame(
            array('to' => 'a@example.com', 'subject' => 'Sample Event: an update', 'message' => 'Wrapped', 'headers' => array()),
            $this->email()->render(null, 'a@example.com')
        );
    }

    public function test_send_mails_exactly_what_render_returns(): void
    {
        global $wp_test_mail;
        $this->brand();
        $context = array('event_title' => 'Fall Expo', 'attendee_name' => 'Sam', 'message' => 'Hello.');

        $rendered = $this->email()->render($context, 'sam@example.com');
        $sent     = $this->email()->send('sam@example.com', $context);

        $this->assertTrue($sent);
        $this->assertCount(1, $wp_test_mail);
        $this->assertSame(array_values($rendered), array_values($wp_test_mail[0]));
    }

    public function test_send_reports_a_wp_mail_failure(): void
    {
        $GLOBALS['wp_test_mail_result'] = false;

        $this->assertFalse($this->email()->send('sam@example.com', array()));
    }

    public function test_render_sends_nothing(): void
    {
        global $wp_test_mail;

        $this->email()->render();

        $this->assertSame(array(), $wp_test_mail);
    }

    public function test_construction_without_an_id_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WP_Settings_Email(array('subject' => 'Hi'));
    }

    public function test_construction_with_a_blank_id_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WP_Settings_Email(array('id' => '  '));
    }
}
