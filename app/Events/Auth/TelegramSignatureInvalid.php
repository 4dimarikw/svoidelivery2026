<?php

namespace App\Events\Auth;

use App\Events\LoggableEvent;

/**
 * Подпись Telegram-логина (HMAC) не совпала — единственная причина отказа
 * входа через Telegram, которая законно не должна встречаться при нормальной
 * работе (в отличие от протухшей auth_date или отсутствующего активного
 * бота — те уходят только в security-канал, см.
 * App\Http\Controllers\Auth\TelegramLoginController::logAuthFailure()).
 * Попадает в event_logs через App\Listeners\PersistEventLog (автодискавери
 * по LoggableEvent) — сигнал подбора/подделки параметров входа.
 */
final readonly class TelegramSignatureInvalid implements LoggableEvent
{
    public function __construct(
        public string $source, // widget | webapp
        public ?string $ip,
    ) {}

    public function eventType(): string
    {
        return 'auth.telegram_signature_invalid';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Подпись Telegram-логина не совпала (источник: {$this->source}).";
    }

    public function context(): array
    {
        return [
            'source' => $this->source,
            'ip' => $this->ip,
        ];
    }
}
