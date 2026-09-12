<?php

namespace App\Events;

/**
 * Ошибка получения имени бота через getMe при сохранении TelegramBot. Имя используется виджетом входа и ссылкой
 * привязки; событие регистрируется в event_logs через PersistEventLog.
 */
final readonly class TelegramBotUsernameFetchFailed implements LoggableEvent
{
    public function __construct(
        public int $botId,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'telegram.bot_username_fetch_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Не удалось получить @username Telegram-бота #{$this->botId}: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'bot_id' => $this->botId,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
