<?php

declare(strict_types=1);

namespace Services\Vk\Support;

final class VkTextFormatter
{
    private const string VK_LINK = '~\[([^\[\]\r\n|]+)\|([^\[\]\r\n]*)\]~u';

    public function forImport(string $text): string
    {
        return preg_replace_callback(self::VK_LINK, fn (array $match) => $this->validUrl($match[1])
            ? $this->anchor($match[1], $match[2]) : $match[0], $text) ?? $text;
    }

    private function validUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function anchor(string $url, string $label): string
    {
        return '<a href="'.$this->escape($url).'">'.$this->escape($label).'</a>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
