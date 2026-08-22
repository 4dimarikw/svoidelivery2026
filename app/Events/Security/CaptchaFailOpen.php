<?php

namespace App\Events\Security;

use App\Events\LoggableEvent;

/**
 * Infrastructure\Rules\SmartCaptchaRule пропустила регистрацию без проверки
 * токена (пустой server_key, сбой HTTP-запроса к Яндексу, не-2xx ответ) —
 * каждая ветка уже пишет в Log::channel('security'), но этот канал никто не
 * читает, а постоянно сломанный server_key даёт молчаливое отключение защиты
 * от ботов. Попадает в event_logs через App\Listeners\PersistEventLog
 * (автодискавери по LoggableEvent).
 *
 * Задушено дедупом на стороне вызывающего кода (Cache::add, 5 минут на
 * причину) — правило срабатывает на каждую регистрацию, событие в event_logs
 * не должно.
 */
final readonly class CaptchaFailOpen implements LoggableEvent
{
    public function __construct(
        public string $reason, // no_server_key | request_failed | bad_status
        public ?int $httpStatus = null,
        public ?string $errorMessage = null,
    ) {}

    public function eventType(): string
    {
        return 'security.captcha_fail_open';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function message(): string
    {
        return match ($this->reason) {
            'no_server_key' => 'SmartCaptcha не настроена (пустой server_key) — регистрация проходит без проверки капчи.',
            'request_failed' => 'SmartCaptcha недоступна (сбой запроса) — регистрация проходит без проверки капчи.',
            'bad_status' => 'SmartCaptcha вернула ошибочный статус — регистрация проходит без проверки капчи.',
            default => 'SmartCaptcha пропустила проверку (fail-open).',
        };
    }

    public function context(): array
    {
        return [
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
            'error_message' => $this->errorMessage,
        ];
    }
}
