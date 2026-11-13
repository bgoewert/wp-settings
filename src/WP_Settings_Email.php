<?php

namespace BGoewert\WP_Settings;

if (!defined('ABSPATH')) {
    die();
}

if (class_exists('BGoewert\\WP_Settings\\WP_Settings_Email')) {
    return;
}

/**
 * One email: a subject and plain-text body with `{placeholder}` tokens, sent and previewed through `wp_settings_email`.
 *
 * The filter takes the array core's `wp_new_user_notification_email` takes, so a
 * site that wraps core's system mail can wrap these with the same callback.
 */
class WP_Settings_Email
{
    public const FILTER = 'wp_settings_email';

    public const SCRIPT_HANDLE = 'wp-settings-email-preview';

    /*
     * Alphanumeric so that escaping, the one thing every wrapper does to a plain
     * body, leaves them findable. `.invalid` is reserved, so the URL never resolves.
     */
    private const SENTINEL_SUBJECT = 'wpssentinelsubject';
    private const SENTINEL_A1      = 'wpssentinelaone';
    private const SENTINEL_A2      = 'wpssentinelatwo';
    private const SENTINEL_B       = 'wpssentinelb';
    private const SENTINEL_URL     = 'https://wps-sentinel.invalid/u';

    /** @var string */
    public $id;

    /** @var string */
    public $subject;

    /** @var string */
    public $body;

    /** @var array<string,string> */
    public $sample;

    /** @var string|string[] */
    public $headers;

    /**
     * @param array{id:string,subject?:string,body?:string,sample?:array<string,string>,headers?:string|string[]} $args
     * @throws \InvalidArgumentException When `id` is missing or blank.
     */
    public function __construct(array $args)
    {
        $id = trim((string) ($args['id'] ?? ''));
        if ('' === $id) {
            throw new \InvalidArgumentException('A WP_Settings_Email needs an id.');
        }

        $this->id      = $id;
        $this->subject = (string) ($args['subject'] ?? '');
        $this->body    = (string) ($args['body'] ?? '');
        $this->sample  = array_map('strval', (array) ($args['sample'] ?? array()));
        $this->headers = $args['headers'] ?? array();
    }

    /**
     * The email as `send()` would hand it to wp_mail(): `to`, `subject`, `message`, `headers`.
     *
     * @param array<string,string>|null $context Placeholder values; the sample when null.
     * @return array{to:string|string[],subject:string,message:string,headers:string|string[]}
     */
    public function render(?array $context = null, $to = ''): array
    {
        $context ??= $this->sample;

        return $this->compose($this->subject, $this->body, $context, $to);
    }

    /**
     * Mail the email to `$to` with `$context` filled in, returning wp_mail()'s result.
     *
     * @param string|string[]      $to
     * @param array<string,string> $context
     */
    public function send($to, array $context): bool
    {
        $email = $this->render($context, $to);

        return (bool) \wp_mail($email['to'], $email['subject'], $email['message'], $email['headers']);
    }

    /**
     * Replace each `{name}` with its context value, leaving tokens the context lacks as written.
     *
     * @param array<string,string> $context
     */
    public static function fill(string $template, array $context): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z0-9_]+)\}/',
            static fn(array $m): string => array_key_exists($m[1], $context) ? (string) $context[$m[1]] : $m[0],
            $template
        );
    }

    /**
     * Whether headers declare an HTML body, which is how a wrapper says it produced one.
     *
     * @param string|string[] $headers
     */
    public static function is_html($headers): bool
    {
        $lines = is_array($headers) ? $headers : preg_split('/\r\n|\r|\n/', (string) $headers);

        foreach ((array) $lines as $line) {
            if (preg_match('/^\s*content-type\s*:\s*text\/html\b/i', (string) $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The preview: the sample's subject, then its message in a sandboxed frame or, for plain text, a `<pre>`.
     *
     * Loads the repaint script itself, so it works in a metabox as well as a settings field.
     *
     * @param array{watch?:array{subject?:string,body?:string},title?:string} $args
     *   `watch` names the element ids of the subject and body inputs to follow.
     */
    public function preview_markup(array $args = array()): string
    {
        $email = $this->render();
        $html  = self::is_html($email['headers']);
        $watch = array_filter(array_map('strval', array_intersect_key((array) ($args['watch'] ?? array()), array('subject' => 1, 'body' => 1))));
        $title = (string) ($args['title'] ?? sprintf('Preview: %s', $this->id));

        $out = sprintf('<figure class="wps-email-preview" aria-label="%s" style="margin:0 0 1em;">', \esc_attr($title));
        $out .= sprintf(
            '<p class="wps-email-preview-subject"><strong>Subject:</strong> <span data-wps-email-subject>%s</span></p>',
            htmlspecialchars($email['subject'], ENT_QUOTES, 'UTF-8')
        );

        if ($html) {
            /*
             * htmlspecialchars() rather than esc_attr(), which leaves existing
             * entities alone: a `&quot;` around a font name would decode to a bare
             * quote and end its style attribute. No sandbox permissions: nothing
             * in a message should run beside the settings form.
             */
            $out .= sprintf(
                '<iframe class="wps-email-preview-frame" title="%s" sandbox="" srcdoc="%s" style="display:block;width:100%%;max-width:720px;height:32em;border:1px solid #c3c4c7;background:#fff;"></iframe>',
                \esc_attr($title),
                htmlspecialchars($email['message'], ENT_QUOTES, 'UTF-8')
            );
        } else {
            $out .= sprintf(
                '<pre class="wps-email-preview-text" style="white-space:pre-wrap;max-width:720px;padding:1em;border:1px solid #c3c4c7;background:#fff;">%s</pre>',
                htmlspecialchars($email['message'], ENT_QUOTES, 'UTF-8')
            );
        }

        if ($watch) {
            $template = $this->live_template();
            if (null === $template) {
                $out .= '<p class="description">The preview updates when the settings are saved.</p>';
            } else {
                $out .= sprintf(
                    '<script type="application/json" class="wps-email-preview-data">%s</script>',
                    \wp_json_encode(array('watch' => $watch, 'sample' => $this->sample, 'template' => $template), JSON_HEX_TAG)
                );
                self::enqueue();
            }
        }

        return $out . '</figure>';
    }

    /**
     * The markup a site wrapper puts around a subject, a paragraph, a line break and a link.
     *
     * Found by rendering sentinels through the filter, so the browser can rebuild
     * the message from typed text without asking the server. Null when the
     * wrapper's output does not show where the text went.
     *
     * @return array<string,mixed>|null
     */
    public function live_template(): ?array
    {
        $a1   = self::SENTINEL_A1;
        $a2   = self::SENTINEL_A2;
        $b    = self::SENTINEL_B;
        $url  = self::SENTINEL_URL;
        $body = "$a1\n$a2\n\n$b $url";

        $email   = $this->compose(self::SENTINEL_SUBJECT, $body, $this->sample, '');
        $subject = self::split_once($email['subject'], self::SENTINEL_SUBJECT);
        if (null === $subject) {
            return null;
        }

        $message = $email['message'];

        if (!self::is_html($email['headers'])) {
            $around = self::split_once($message, $body);

            return null === $around ? null : array('html' => false, 'subject' => $subject, 'body' => $around);
        }

        foreach (array($a1, $a2, $b) as $sentinel) {
            if (1 !== substr_count($message, $sentinel)) {
                return null;
            }
        }

        $p_a1 = strpos($message, $a1);
        $p_a2 = strpos($message, $a2);
        $p_b  = strpos($message, $b);
        if (!($p_a1 < $p_a2 && $p_a2 < $p_b)) {
            return null;
        }

        $line_break = substr($message, $p_a1 + strlen($a1), $p_a2 - $p_a1 - strlen($a1));
        $before     = substr($message, 0, $p_a1);
        $between    = substr($message, $p_a2 + strlen($a2), $p_b - $p_a2 - strlen($a2));

        $link = self::link_template(substr($message, $p_b + strlen($b)), $url);
        if (null === $link) {
            return null;
        }

        $after = $link['after'];

        $open_length  = self::common_suffix_length($before, $between);
        $close_length = min(self::common_prefix_length($between, $after), strlen($between) - $open_length);

        return array(
            'html'      => true,
            'subject'   => $subject,
            'prefix'    => substr($before, 0, strlen($before) - $open_length),
            'open'      => substr($between, strlen($between) - $open_length),
            'close'     => substr($between, 0, $close_length),
            'separator' => substr($between, $close_length, strlen($between) - $open_length - $close_length),
            'suffix'    => substr($after, $close_length),
            'lineBreak' => $line_break,
            'link'      => $link['parts'],
        );
    }

    /**
     * Enqueue the script that repaints previews as their watched inputs change.
     */
    public static function enqueue(): void
    {
        $file = __DIR__ . '/assets/admin-email-preview.js';

        \wp_enqueue_script(
            self::SCRIPT_HANDLE,
            \plugin_dir_url(__FILE__) . 'assets/admin-email-preview.js',
            array(),
            file_exists($file) ? (string) filemtime($file) : false,
            true
        );
    }

    /**
     * @param array<string,string> $context
     * @param string|string[]      $to
     * @return array{to:string|string[],subject:string,message:string,headers:string|string[]}
     */
    private function compose(string $subject, string $body, array $context, $to): array
    {
        $email = array(
            'to'      => $to,
            'subject' => self::fill($subject, $context),
            'message' => self::fill($body, $context),
            'headers' => $this->headers,
        );

        $filtered = \apply_filters(self::FILTER, $email, $this, $context);

        if (!is_array($filtered) || !isset($filtered['message']) || !is_string($filtered['message'])) {
            return $email;
        }

        $filtered = array_merge($email, array_intersect_key($filtered, $email));
        $filtered['subject'] = (string) $filtered['subject'];

        return $filtered;
    }

    /**
     * The link markup after paragraph two's "B ", with the URL as the gaps between parts.
     *
     * @return array{parts:string[],after:string}|null
     */
    private static function link_template(string $tail, string $url): ?array
    {
        // Paragraph two is "B <url>": what the typed space became must be a space.
        if (' ' !== substr($tail, 0, 1)) {
            return null;
        }
        $tail = substr($tail, 1);

        $first = strpos($tail, $url);
        $last  = strrpos($tail, $url);
        if (false === $first || false === $last) {
            return null;
        }

        // Inside a tag when the nearest '<' before it is not yet closed.
        $lt    = strrpos(substr($tail, 0, $first), '<');
        $gt    = strrpos(substr($tail, 0, $first), '>');
        $start = (false !== $lt && (false === $gt || $lt > $gt)) ? $lt : $first;
        if (0 !== $start) {
            return null;
        }

        $end = $last + strlen($url);
        // A link that opened with a tag ends with its closing tag; a bare URL ends where it ends.
        if ($start !== $first && '</' === substr($tail, $end, 2)) {
            $gt_after = strpos($tail, '>', $end);
            if (false === $gt_after) {
                return null;
            }
            $end = $gt_after + 1;
        }

        return array(
            'parts' => explode($url, substr($tail, 0, $end)),
            'after' => substr($tail, $end),
        );
    }

    /**
     * The text before and after the one occurrence of `$needle`, or null.
     *
     * @return string[]|null
     */
    private static function split_once(string $haystack, string $needle): ?array
    {
        if (1 !== substr_count($haystack, $needle)) {
            return null;
        }

        return explode($needle, $haystack, 2);
    }

    private static function common_prefix_length(string $a, string $b): int
    {
        $max = min(strlen($a), strlen($b));
        $i   = 0;
        while ($i < $max && $a[$i] === $b[$i]) {
            $i++;
        }

        return $i;
    }

    private static function common_suffix_length(string $a, string $b): int
    {
        return self::common_prefix_length(strrev($a), strrev($b));
    }
}
