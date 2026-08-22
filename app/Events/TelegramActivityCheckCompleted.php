<?php

namespace App\Events;

/**
 * Итог прогона `php artisan telegram:check-activity` — попадает в event_logs
 * через App\Listeners\PersistEventLog (автодискавери по LoggableEvent).
 * Результат напрямую сужает аудиторию VK→Telegram рассылки
 * (Infrastructure\Jobs\BroadcastVkPostJob фильтрует по is_bot_active), поэтому
 * команда раньше возвращавшая self::SUCCESS без единой записи в журнале была
 * слепым пятном.
 */
final readonly class TelegramActivityCheckCompleted implements LoggableEvent
{
    /** @param  list<array{user_id: int, error_message: string}>  $errors */
    public function __construct(
        public int $processed,
        public int $active,
        public int $inactive,
        public array $errors,
    ) {}

    public function eventType(): string
    {
        return 'telegram.activity_check_completed';
    }

    public function level(): string
    {
        return $this->errors !== [] ? 'warning' : 'info';
    }

    public function message(): string
    {
        return "Проверка активности Telegram-ботов: обработано {$this->processed}, активно {$this->active}, недоступно {$this->inactive}.";
    }

    public function context(): array
    {
        return [
            'processed' => $this->processed,
            'active' => $this->active,
            'inactive' => $this->inactive,
            'errors_count' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}
