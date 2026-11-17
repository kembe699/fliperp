<?php

namespace App\Support;

/**
 * FlipERP's own product branding for server-rendered output (PDFs, emails).
 * Files come from brand/ (see brand/BRAND.md), copied unchanged into public/brand.
 * A client's own company logo is separate (Company::logoDataUri) and is
 * never replaced by this.
 */
class Brand
{
    public const LOGO_PNG = 'brand/logo/fliperp-logo-horizontal.png';

    protected static ?string $logoDataUri = null;

    /** The horizontal logo as a data: URI, for dompdf (which cannot fetch remote files). */
    public static function logoDataUri(): ?string
    {
        if (static::$logoDataUri === null) {
            $path = public_path(self::LOGO_PNG);
            static::$logoDataUri = is_readable($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : '';
        }

        return static::$logoDataUri ?: null;
    }

    /** Absolute URL of the horizontal PNG logo, for emails (clients don't show SVG). */
    public static function logoUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::LOGO_PNG;
    }
}
