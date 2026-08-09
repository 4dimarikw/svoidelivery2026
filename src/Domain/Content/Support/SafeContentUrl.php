<?php

namespace Domain\Content\Support;

final class SafeContentUrl
{
    public static function resolve(?string $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || str_starts_with($url, '//') || preg_match('/[\x00-\x20\x7f]/u', $url)) {
            return null;
        }

        if (str_starts_with($url, '#')) {
            return preg_match('/^#[A-Za-z0-9_-]+$/', $url) === 1 ? $url : null;
        }

        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') ? null : $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
