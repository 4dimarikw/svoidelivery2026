<?php

namespace App\Events;

/**
 * Domain\Telegram\Models\TelegramBot::booted()'s saved()-хук не смог получить
 * юзернейм бота через getMe (невалидный токен или Telegram недоступен) —
 * раньше гасилось голым catch (Throwable) { return; }, и бот тихо сохранялся
 * с username = null, что ломает и виджет входа (<x-ui.telegram-login-button>
 * читает TelegramBot::current()?->username), и deep-link привязки. Попадает
 * в event_logs через App\Listeners\PersistEventLog (автодискавери по
 * LoggableEvent). Событий немного — только при сохранении записи бота,
 * агрегация не нужна.
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
