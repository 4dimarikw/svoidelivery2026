<?php

namespace Domain\Telegram\Support;

final class TelegramAuthFields
{
    public static function positiveInteger(mixed $value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }
        if (! preg_match('/\A[1-9][0-9]*\z/D', (string) $value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }

    public static function dateFailure(mixed $value, int $ttl): ?string
    {
        $date = self::positiveInteger($value);
        if ($date === null) {
            return 'invalid_auth_date';
        }
        $now = now()->timestamp;
        if ($date > $now + max(0, (int) config('telegram-auth.future_skew_seconds', 30))) {
            return 'future_auth_date';
        }

        return $date < $now - $ttl ? 'stale_auth_date' : null;
    }

    public static function scalarFields(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if (! preg_match('/\A[A-Za-z0-9_]+\z/D', (string) $key)
                || (! is_string($value) && ! is_int($value))
                || (is_string($value) && preg_match('/[\r\n\x00]/', $value))) {
                return false;
            }
        }

        return true;
    }
}
