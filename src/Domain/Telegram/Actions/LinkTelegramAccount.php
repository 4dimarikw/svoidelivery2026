<?php

namespace Domain\Telegram\Actions;

use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramIdentityTransaction;
use Domain\Telegram\Support\TelegramLinkCode;
use Domain\Telegram\Support\TelegramLinkResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LinkTelegramAccount
{
    public function handle(string $code, int $botId, string $chatId, ?int $senderId, string $chatType, bool $senderIsBot, string $name): TelegramLinkResult
    {
        if ($chatType !== 'private' || $senderId === null || $senderId <= 0 || $senderIsBot || $chatId !== (string) $senderId) {
            return TelegramLinkResult::InvalidSender;
        }
        if (! preg_match('/\A[A-Za-z0-9]{32}\z/', $code)) {
            return TelegramLinkResult::InvalidCode;
        }

        return app(TelegramIdentityTransaction::class)->run([$botId], function ($bots) use ($code, $botId, $chatId, $name) {
            if ($bots->isEmpty()) {
                return TelegramLinkResult::InvalidCode;
            }
            $token = DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $code))
                ->where('telegraph_bot_id', $botId)->lockForUpdate()->first();
            if ($token === null || Carbon::parse($token->expires_at)->lessThanOrEqualTo(now())) {
                return TelegramLinkResult::InvalidCode;
            }
            $column = TelegramLinkCode::subjectColumn($token->subject);
            $account = DB::table(TelegramLinkCode::subjectTable($token->subject))
                ->where('id', $token->subject_id)->lockForUpdate()->first();
            if ($account === null) {
                return TelegramLinkResult::InvalidCode;
            }
            $chats = TelegramChat::query()->where('telegraph_bot_id', $botId)
                ->where(fn ($query) => $query->where('chat_id', $chatId)->orWhere($column, $token->subject_id))
                ->orderBy('id')->lockForUpdate()->get();
            $target = $chats->first(fn ($chat) => (string) $chat->chat_id === $chatId);
            if ($token->consumed_at !== null) {
                return $token->consumed_chat_id === $chatId && $target !== null && (int) $target->$column === (int) $token->subject_id
                    ? TelegramLinkResult::AlreadyLinked : TelegramLinkResult::InvalidCode;
            }
            if ($target !== null && $target->$column !== null && (int) $target->$column !== (int) $token->subject_id) {
                return TelegramLinkResult::Occupied;
            }
            foreach ($chats as $chat) {
                if ((string) $chat->chat_id !== $chatId && (int) $chat->$column === (int) $token->subject_id) {
                    $chat->update([$column => null]);
                }
            }
            $target ??= new TelegramChat(['chat_id' => $chatId, 'telegraph_bot_id' => $botId]);
            $target->fill([$column => $token->subject_id, 'name' => $target->name ?: $name])->save();
            DB::table('telegram_link_codes')->where('id', $token->id)
                ->update(['consumed_at' => now(), 'consumed_chat_id' => $chatId]);

            return $token->subject === TelegramLinkCode::SUBJECT_USER ? TelegramLinkResult::Linked : TelegramLinkResult::LinkedAdmin;
        });
    }
}
