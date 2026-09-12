<?php

namespace Domain\Telegram\Actions;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramIdentityTransaction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final class FindOrRegisterTelegramUser
{
    /**
     * HTTP-обработчик проверяет идентификатор и подпись перед вызовом действия.
     */
    public function handle(int $botId, int $chatId, string $name, ?string $username): User
    {
        return app(TelegramIdentityTransaction::class)->run([$botId], function ($bots) use ($botId, $chatId, $name, $username) {
            abort_if($bots->isEmpty(), 404);
            // Блокировка бота сериализует операции, включая создание незарегистрированного чата.
            $userId = TelegramChat::query()->where('telegraph_bot_id', $botId)->where('chat_id', $chatId)->value('user_id');
            $user = $userId === null ? null : User::query()->whereKey($userId)->lockForUpdate()->first();
            $newUser = $user === null;
            if ($newUser) {
                $user = User::create(['name' => $name]);
                // Связанные записи создаются внутри транзакции; событие Registered допускает повторную обработку
                // после фиксации.
                $user->profile()->create($username !== null && $username !== ''
                    ? ['social_links' => ['telegram' => "https://t.me/{$username}"]] : []);
                DB::afterCommit(fn () => event(new Registered($user)));
            }
            $chat = TelegramChat::query()->where('telegraph_bot_id', $botId)->where('chat_id', $chatId)
                ->lockForUpdate()->first();
            if ($newUser) {
                ($chat ?? new TelegramChat(['chat_id' => $chatId, 'telegraph_bot_id' => $botId]))
                    ->fill(['user_id' => $user->id, 'name' => $name])->save();
            }

            return $user;
        });
    }
}
