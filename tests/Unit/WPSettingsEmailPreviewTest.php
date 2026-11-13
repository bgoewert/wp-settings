<?php

use BGoewert\WP_Settings\WP_Settings_Email;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/WPSettingsEmailTest.php';

/**
 * An email's preview, and the template the browser repaints it from.
 */
class WPSettingsEmailPreviewTest extends WP_Settings_TestCase
{
    private const WATCH = array('subject' => 'my_plugin_notice_subject', 'body' => 'my_plugin_notice_body');

    private function email(): WP_Settings_Email
    {
        return new WP_Settings_Email(array(
            'id'      => 'notice',
            'subject' => '{event_title}: an update',
            'body'    => "Hi {attendee_name},\n\nDoors open at 9. Details: https://example.com/expo?a=1&b=2.",
            'sample'  => array('event_title' => 'Sample Event', 'attendee_name' => 'Jane "JD" Doe'),
        ));
    }

    private function brand(): void
    {
        add_filter('wp_settings_email', array(WPSettingsEmailTest::class, 'branded'), 20);
    }

    private function srcdoc(string $markup): ?string
    {
        if (!preg_match('~<iframe[^>]* srcdoc="([^"]*)"~', $markup, $m)) {
            return null;
        }

        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    private function data(string $markup): ?array
    {
        if (!preg_match('~<script type="application/json" class="wps-email-preview-data">(.*?)</script>~s', $markup, $m)) {
            return null;
        }

        return json_decode($m[1], true);
    }

    /**
     * What the browser script builds from the template, transcribed, so a derived template can be checked against a real render.
     */
    private function assemble(array $t, string $subject, string $body, array $sample): array
    {
        $subject = $t['subject'][0] . WP_Settings_Email::fill($subject, $sample) . $t['subject'][1];
        $body    = WP_Settings_Email::fill($body, $sample);

        if (!$t['html']) {
            return array($subject, $t['body'][0] . $body . $t['body'][1]);
        }

        $text = htmlspecialchars(str_replace(array("\r\n", "\r"), "\n", $body), ENT_QUOTES, 'UTF-8');
        $text = (string) preg_replace_callback('~\bhttps?://[^\s<]+~', static function (array $m) use ($t): string {
            $url = $m[0];
            $trail = '';
            while ('' !== $url && false !== strpos('.,!?)', substr($url, -1))) {
                $trail = substr($url, -1) . $trail;
                $url   = substr($url, 0, -1);
            }
            return implode($url, $t['link']) . $trail;
        }, $text);

        $paragraphs = array();
        foreach (preg_split('/\n{2,}/', trim($text)) as $p) {
            $p = trim($p);
            if ('' !== $p) {
                $paragraphs[] = $t['open'] . str_replace("\n", $t['lineBreak'], $p) . $t['close'];
            }
        }

        return array($subject, $t['prefix'] . implode($t['separator'], $paragraphs) . $t['suffix']);
    }

    public function test_a_wrapped_email_shows_in_a_sandboxed_titled_frame_exactly(): void
    {
        $this->brand();
        $email  = $this->email();
        $markup = $email->preview_markup();

        $this->assertMatchesRegularExpression('~<iframe class="wps-email-preview-frame" title="Preview: notice" sandbox="" srcdoc=~', $markup);
        $this->assertSame($email->render()['message'], $this->srcdoc($markup), 'entities included');
        $this->assertStringContainsString('&amp;quot;Inter&amp;quot;', $markup, 'an entity in the message survives as an entity');
        $this->assertStringNotContainsString('<pre', $markup);
    }

    public function test_the_subject_shows_as_text_above_the_message(): void
    {
        $this->brand();

        $this->assertStringContainsString('<span data-wps-email-subject>[Seiler] Sample Event: an update</span>', $this->email()->preview_markup());
    }

    public function test_an_unwrapped_email_shows_as_preformatted_text(): void
    {
        $markup = $this->email()->preview_markup(array('title' => 'Attendee notice'));

        $this->assertStringContainsString('aria-label="Attendee notice"', $markup);
        $this->assertStringContainsString("Hi Jane &quot;JD&quot; Doe,\n\nDoors open at 9.", $markup);
        $this->assertStringNotContainsString('<iframe', $markup);
    }

    public function test_an_entity_typed_in_plain_text_shows_as_typed(): void
    {
        $markup = (new WP_Settings_Email(array('id' => 'x', 'subject' => 'AT&amp;T', 'body' => 'AT&amp;T')))->preview_markup();

        $this->assertStringContainsString('<span data-wps-email-subject>AT&amp;amp;T</span>', $markup);
        $this->assertMatchesRegularExpression('~<pre[^>]*>AT&amp;amp;T</pre>~', $markup);
    }

    public function test_html_is_detected_from_the_headers_not_the_message(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] = '<p>Looks like HTML</p>';
            return $email;
        });

        $this->assertStringNotContainsString('<iframe', $this->email()->preview_markup());
        $this->assertTrue(WP_Settings_Email::is_html("From: a@example.com\r\nContent-Type: text/html; charset=UTF-8"));
        $this->assertFalse(WP_Settings_Email::is_html(array('Content-Type: text/plain')));
    }

    public function test_without_watch_there_is_no_script_or_data(): void
    {
        global $wp_test_enqueued_scripts;
        $this->brand();

        $markup = $this->email()->preview_markup();

        $this->assertNull($this->data($markup));
        $this->assertNotContains(WP_Settings_Email::SCRIPT_HANDLE, $wp_test_enqueued_scripts);
    }

    public function test_watching_prints_the_template_beside_the_frame_and_loads_the_script(): void
    {
        global $wp_test_enqueued_scripts;
        $this->brand();

        $data = $this->data($this->email()->preview_markup(array('watch' => self::WATCH)));

        $this->assertSame(self::WATCH, $data['watch']);
        $this->assertSame('Sample Event', $data['sample']['event_title']);
        $this->assertTrue($data['template']['html']);
        $this->assertContains(WP_Settings_Email::SCRIPT_HANDLE, $wp_test_enqueued_scripts);
    }

    public function test_the_data_cannot_close_its_script_tag(): void
    {
        $email = new WP_Settings_Email(array('id' => 'x', 'body' => 'b', 'sample' => array('m' => '</script><script>alert(1)</script>')));

        $markup = $email->preview_markup(array('watch' => self::WATCH));

        $this->assertSame(1, substr_count($markup, '</script>'));
        $this->assertSame('</script><script>alert(1)</script>', $this->data($markup)['sample']['m']);
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function edits(): array
    {
        return array(
            'two paragraphs and a link' => array('{event_title} news', "Hi {attendee_name},\n\nRead https://example.com/a?b=1&c=2, then reply."),
            'one line'                  => array('Plain', 'Just one line'),
            'line breaks and three'     => array('X', "One\ntwo\n\n\nThree <b>&</b>\n\nFour (https://example.com/x)."),
            'empty body'                => array('', ''),
            'surrounding blank lines'   => array('S', "\n\n  Padded  \n\n"),
            'a bare URL'                => array('S', 'https://example.com'),
        );
    }

    #[DataProvider('edits')]
    public function test_the_template_rebuilds_what_the_server_renders_under_a_wrapper(string $subject, string $body): void
    {
        $this->brand();
        $email = $this->email();
        $t     = $email->live_template();
        $this->assertNotNull($t);

        $expected = (new WP_Settings_Email(array('id' => 'n', 'subject' => $subject, 'body' => $body, 'sample' => $email->sample)))->render();

        $this->assertSame(array($expected['subject'], $expected['message']), $this->assemble($t, $subject, $body, $email->sample));
    }

    #[DataProvider('edits')]
    public function test_the_template_rebuilds_what_the_server_renders_with_no_wrapper(string $subject, string $body): void
    {
        $email = $this->email();
        $t     = $email->live_template();

        $expected = (new WP_Settings_Email(array('id' => 'n', 'subject' => $subject, 'body' => $body, 'sample' => $email->sample)))->render();

        $this->assertFalse($t['html']);
        $this->assertSame(array($expected['subject'], $expected['message']), $this->assemble($t, $subject, $body, $email->sample));
    }

    public function test_a_plain_text_wrapper_that_adds_a_footer_is_followed(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] .= "\n\n-- \nSeiler Instrument";
            return $email;
        });
        $email = $this->email();

        $this->assertSame(array('S', "Typed\n\n-- \nSeiler Instrument"), $this->assemble($email->live_template(), 'S', 'Typed', $email->sample));
    }

    public function test_a_wrapper_that_drops_the_body_yields_no_template_and_a_note(): void
    {
        global $wp_test_enqueued_scripts;
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] = '<p>Fixed content</p>';
            $email['headers'] = 'Content-Type: text/html';
            return $email;
        });
        $email = $this->email();

        $this->assertNull($email->live_template());
        $markup = $email->preview_markup(array('watch' => self::WATCH));
        $this->assertStringContainsString('The preview updates when the settings are saved.', $markup);
        $this->assertNull($this->data($markup));
        $this->assertNotContains(WP_Settings_Email::SCRIPT_HANDLE, $wp_test_enqueued_scripts);
    }

    public function test_watch_keeps_only_a_named_subject_and_body(): void
    {
        $markup = $this->email()->preview_markup(array('watch' => array('subject' => 'subj', 'body' => '', 'footer' => 'foot')));

        $this->assertSame(array('subject' => 'subj'), $this->data($markup)['watch']);
        $this->assertStringStartsWith('<figure', $markup);
        $this->assertStringEndsWith('</figure>', $markup);
    }

    public function test_the_note_follows_the_preview_rather_than_replacing_it(): void
    {
        add_filter('wp_settings_email', static fn($email) => array('message' => '<p>Fixed</p>', 'headers' => 'Content-Type: text/html') + $email);

        $markup = $this->email()->preview_markup(array('watch' => self::WATCH));

        $this->assertMatchesRegularExpression('~<iframe[^>]*></iframe><p class="description">The preview updates~', $markup);
    }

    public function test_a_wrapper_that_reorders_paragraphs_yields_no_template(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $paragraphs       = array_reverse(preg_split('/\n{2,}/', $email['message']));
            $email['message'] = '<p>' . implode('</p><p>', $paragraphs) . '</p>';
            $email['headers'] = 'Content-Type: text/html';
            return $email;
        });

        $this->assertNull($this->email()->live_template());
    }

    public function test_a_wrapper_that_turns_the_space_before_a_link_into_markup_yields_no_template(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] = '<p>' . str_replace(' ', '&nbsp;', $email['message']) . '</p>';
            $email['headers'] = 'Content-Type: text/html';
            return $email;
        });

        $this->assertNull($this->email()->live_template());
    }

    public function test_a_wrapper_that_drops_the_url_yields_no_template(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] = '<p>' . preg_replace('~https?://\S+~', '[link]', $email['message']) . '</p>';
            $email['headers'] = 'Content-Type: text/html';
            return $email;
        });

        $this->assertNull($this->email()->live_template());
    }

    public function test_a_wrapper_that_replaces_the_subject_yields_no_template(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['subject'] = 'Notice';
            return $email;
        });

        $this->assertNull($this->email()->live_template());
    }

    public function test_a_wrapper_that_repeats_the_body_yields_no_template(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $email['message'] = '<p>' . $email['message'] . '</p><p>' . $email['message'] . '</p>';
            $email['headers'] = 'Content-Type: text/html';
            return $email;
        });

        $this->assertNull($this->email()->live_template());
    }

    public function test_a_wrapper_that_leaves_urls_unlinked_still_rebuilds(): void
    {
        add_filter('wp_settings_email', static function ($email) {
            $text = htmlspecialchars($email['message'], ENT_QUOTES, 'UTF-8');
            $email['message'] = '<div>' . implode('', array_map(static fn($p) => '<p>' . nl2br($p, false) . '</p>', preg_split('/\n{2,}/', trim($text)))) . '</div>';
            $email['headers'] = array('Content-Type: text/html');
            return $email;
        });
        $email = $this->email();
        $body  = "First\nline\n\nSee https://example.com now";

        $expected = (new WP_Settings_Email(array('id' => 'n', 'body' => $body)))->render()['message'];

        $this->assertSame($expected, $this->assemble($email->live_template(), '', $body, $email->sample)[1]);
    }
}
