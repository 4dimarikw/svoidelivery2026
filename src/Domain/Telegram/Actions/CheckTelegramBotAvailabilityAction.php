<?php

declare(strict_types=1);

namespace Domain\Telegram\Actions;

use DefStudio\Telegraph\Enums\ChatActions;
use Domain\Auth\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Проверяет, может ли бот написать пользователю, и пишет результат в его
 * профиль (Profile::$is_bot_active/$last_bot_error/$bot_checked_at).
 *
 * Механика — sendChatAction(TYPING), а не sendMessage: самый дешёвый запрос
 * к Bot API (пользователю ничего не приходит, кроме мигающего «печатает…»),
 * но Telegram возвращает те же ошибки доступа, что и на отправку сообщения
 * (403 Forbidden: bot was blocked by the user, 400 Bad Request: chat not
 * found) — этого достаточно, чтобы отличить рабочий чат от нерабочего.
 */
final class CheckTelegramBotAvailabilityAction
{
    public function __invoke(User $user): bool
    {
        $user->loadMissing('telegramChat');

        $chat = $user->telegramChat;

        if ($chat === null) {
            $this->writeResult($user, false, 'Telegram не привязан');

            return false;
        }

        try {
            // ChatActions — не enum, а класс со строковыми константами
            // (defstudio/telegraph), поэтому TYPING уже готовая строка.
            $response = $chat->action(ChatActions::TYPING)->send();
        } catch (Throwable $e) {
            $this->writeResult($user, false, $e->getMessage());

            return false;
        }

        if ($response->telegraphOk()) {
            $this->writeResult($user, true, null);

            return true;
        }

        $error = $response->json('description') ?? ('HTTP '.$response->status());
        $this->writeResult($user, false, $error);

        return false;
    }

    private function writeResult(User $user, bool $isActive, ?string $error): void
    {
        $user->profile()->updateOrCreate([], [
            'is_bot_active' => $isActive,
            'last_bot_error' => $error !== null ? Str::limit($error, 1000) : null,
            'bot_checked_at' => now(),
        ]);
    }
}
