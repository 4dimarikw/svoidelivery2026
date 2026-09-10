<?php

namespace Domain\Telegram\Actions;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramIdentityTransaction;
use Domain\Telegram\Support\TelegramLinkCode;
use Illuminate\Support\Facades\DB;

final class UnlinkTelegramAccount
{
    public function handle(string $subject, int $id): void
    {
        app(TelegramIdentityTransaction::class)->run(null, function () use ($subject, $id) {
            $column = TelegramLinkCode::subjectColumn($subject);
            if ($subject === TelegramLinkCode::SUBJECT_USER) {
                $user = User::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless($user->canUnlinkTelegram(), 403);
            } else {
                if (DB::table(TelegramLinkCode::subjectTable($subject))->where('id', $id)->lockForUpdate()->first() === null) {
                    return;
                }
            }
            $chats = TelegramChat::query()->where($column, $id)->orderBy('id')->lockForUpdate()->get();
            foreach ($chats as $chat) {
                $chat->update([$column => null]);
            }
        });
    }
}
