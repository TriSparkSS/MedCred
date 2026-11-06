<?php

namespace App\Support;

class Branding
{
    public const NAME = 'MedCred';

    /**
     * Base64 data URI of the compact MedCred logo, safe for Dompdf (no remote fetch).
     */
    public static function pdfLogoDataUri(): string
    {
        static $uri = null;

        if ($uri !== null) {
            return $uri;
        }

        $path = public_path('assets/logo/logo-pdf.jpg');
        if (! is_file($path)) {
            $path = public_path('assets/logo/logo.jpeg');
        }

        return $uri = is_file($path)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path))
            : '';
    }
}
