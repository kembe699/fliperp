<?php

namespace App\Support;

/**
 * Plain text typed by staff → safe email HTML: escaped, line breaks kept, and
 * http(s) links made clickable. $href lets campaigns route links through
 * click tracking.
 */
class EmailText
{
    public static function toHtml(string $text, ?callable $href = null): string
    {
        $escaped = e($text);

        $linked = preg_replace_callback(
            '#\bhttps?://[^\s<>"\']+#i',
            function (array $match) use ($href) {
                // Trailing punctuation belongs to the sentence, not the link.
                $url = rtrim($match[0], '.,;:!?)');
                $tail = substr($match[0], strlen($url));
                $raw = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
                $target = $href ? $href($raw) : $raw;

                return '<a href="'.e($target).'" style="color:#1B5FAE;">'.$url.'</a>'.$tail;
            },
            $escaped,
        );

        return nl2br($linked, false);
    }
}
