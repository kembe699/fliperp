<?php

namespace App\Support;

class Url
{
    /**
     * Ensures a URL has an http(s) scheme, defaulting to https.
     *
     * FRONTEND_URL is sometimes configured without one (e.g. a bare Railway
     * domain pasted as-is) — a scheme-less string isn't a valid absolute
     * URL, which breaks anything that parses it (browsers, QR scanners,
     * `new URL()` on the frontend).
     */
    public static function withScheme(string $url): string
    {
        $url = rtrim($url, '/');

        return preg_match('#^https?://#i', $url) ? $url : "https://{$url}";
    }
}
