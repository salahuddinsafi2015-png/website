<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

/**
 * Sanitises editor-supplied HTML before it is stored.
 *
 * Content is stored as HTML (like WordPress) and rendered with dangerously
 * SetInnerHTML on the frontend, so the HTML must be cleaned at write time.
 */
class ContentSanitizer
{
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return Purifier::clean($html);
    }

    /**
     * Plain-text excerpt derived from sanitised HTML.
     */
    public function excerptFrom(?string $html, int $length = 160): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return $text === '' ? null : mb_substr($text, 0, $length);
    }
}
