<?php

namespace Support\Logging;

use Illuminate\Support\Str;

final class LogCorrelation
{
    public static function id(): string
    {
        // ID создаётся сервером, входящий заголовок клиента не используется.
        $request = app('request');
        $id = $request->attributes->get('log_correlation_id');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('log_correlation_id', $id);
        }

        return $id;
    }

    public static function reset(): void
    {
        app('request')->attributes->remove('log_correlation_id');
    }
}
