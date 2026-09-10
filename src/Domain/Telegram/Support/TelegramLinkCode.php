<?php

namespace Domain\Telegram\Support;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TelegramLinkCode
{
    public const SUBJECT_USER = 'user';

    public const SUBJECT_MOONSHINE_USER = 'moonshine_user';

    public static function issue(User $user, ?TelegramBot $bot = null): string
    {
        return self::issueFor(self::SUBJECT_USER, $user->id, $bot);
    }

    public static function issueFor(string $subject, int $id, ?TelegramBot $bot = null): string
    {
        self::subjectColumn($subject);
        $bot ??= TelegramBot::current();
        if ($bot === null) {
            throw new InvalidArgumentException('Telegram bot is required');
        }
        $code = Str::random(32);
        DB::table('telegram_link_codes')->insert([
            'code_hash' => hash('sha256', $code), 'subject' => $subject, 'subject_id' => $id,
            'telegraph_bot_id' => $bot->id, 'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public static function subjectColumn(string $subject): string
    {
        return match ($subject) {
            self::SUBJECT_USER => 'user_id',
            self::SUBJECT_MOONSHINE_USER => 'moonshine_user_id',
            default => throw new InvalidArgumentException('Unknown Telegram link subject'),
        };
    }

    public static function subjectTable(string $subject): string
    {
        return match ($subject) {
            self::SUBJECT_USER => 'users',
            self::SUBJECT_MOONSHINE_USER => 'moonshine_users',
            default => throw new InvalidArgumentException('Unknown Telegram link subject'),
        };
    }
}
