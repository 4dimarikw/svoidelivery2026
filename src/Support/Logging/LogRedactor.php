<?php

namespace Support\Logging;

use Throwable;

final class LogRedactor
{
    public function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 12) {
            return '[REDACTED:depth]';
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[is_string($key) ? $this->text($key) : $key] = $this->sensitiveKey((string) $key)
                    ? '[REDACTED]' : $this->value($item, $depth + 1);
            }

            return $result;
        }
        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $this->text($value->getMessage()),
                'file' => $this->text($value->getFile()),
                'line' => $value->getLine(),
                // Аргументы стека могут содержать пароли и тела запросов.
                'trace' => array_map(fn ($frame) => $this->value(array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function'])), $depth + 1), $value->getTrace()),
                'previous' => $value->getPrevious() ? $this->value($value->getPrevious(), $depth + 1) : null,
            ];
        }
        if (is_object($value)) {
            return '[object '.$value::class.']';
        }
        if (is_resource($value)) {
            return '[resource]';
        }

        return is_string($value) ? $this->text($value) : $value;
    }

    public function sensitiveKey(string $key): bool
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', $key));

        return preg_match('/password|passwd|token|secret|apikey|authorization|bearer|cookie|session|csrf|privatekey|initdata|signature|^hash$|^user$|^args$/', $key) === 1;
    }

    public function text(string $text): string
    {
        $text = str_replace('\\/', '/', $text);
        if (strlen($text) > 65536 || ! preg_match('//u', $text)) {
            return '[REDACTED:binary]';
        }
        // Распознаём также URL-encoded сообщения исключений, не сохраняя исходник.
        for ($i = 0; $i < 2 && preg_match('/%[a-f0-9]{2}/i', $text); $i++) {
            $text = rawurldecode($text);
        }
        if (! preg_match('//u', $text)) {
            return '[REDACTED:binary]';
        }
        if (preg_match('/(?:auth_date|query_id|user)=.*(?:&|\\s)hash=/s', $text)
            || preg_match('/hash=[a-f0-9]{64}.*(?:auth_date|query_id|user)=/s', $text)) {
            return '[REDACTED:Telegram auth data]';
        }
        $patterns = [
            '~\\b(?:Bearer|Basic)\\s+[A-Za-z0-9\\-._\\~+/]+=*~i' => '[REDACTED]',
            '~-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----~s' => '[REDACTED]',
            // Полностью скрываем значения именованных секретов, включая JSON-строки.
            '~(["\']?(?:[a-z0-9_-]*(?:password|passwd|token|secret|api[_-]?key|authorization|cookie|init[_-]?data|private[_-]?key|signature)[a-z0-9_-]*|hash)["\']?\\s*[:=]\\s*)("(?:\\\\.|[^"\\\\])*"|\'[^\']*\'|[^\\s,;&}]+)~i' => '$1[REDACTED]',
            '~(https?://|ftp[s]?://)[^/\\s@]+@~i' => '$1[REDACTED]@',
            '~(/telegraph/)[^/\\s?]+(/webhook)~i' => '$1[REDACTED]$2',
            '~(/bot)[^/\\s?]+(/(?:[A-Za-z][A-Za-z0-9_]*))~' => '$1[REDACTED]$2',
            '~(https?://[^\\s?"\'<>]+)\\?[^\\s"\'<>]*~i' => '$1?[REDACTED]',
            '~\\b[0-9]{5,}:[A-Za-z0-9_-]{20,}\\b~' => '[REDACTED]',
            '~\\bey[A-Za-z0-9_-]{20,}\\.[A-Za-z0-9_-]+\\.[A-Za-z0-9_-]+~' => '[REDACTED]',
            '~\\b[A-Za-z0-9]{32,}\\b~' => '[REDACTED]',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? '[REDACTED:unparseable]';
        }
        $route = config('telegraph.webhook_url', config('telegraph.webhook.url'));
        if (is_string($route) && str_contains($route, '{token}')) {
            $pattern = '~'.str_replace('\\{token\\}', '[^/\\s?]+', preg_quote($route, '~')).'~';
            $text = preg_replace_callback($pattern, fn () => str_replace('{token}', '[REDACTED]', $route), $text) ?? '[REDACTED:route]';
        }

        return $text;
    }
}
