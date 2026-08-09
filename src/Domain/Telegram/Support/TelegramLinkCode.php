<?php

namespace Domain\Telegram\Support;

use Domain\Auth\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Одноразовый код для привязки Telegram к уже авторизованному аккаунту:
 * выдаётся на /account/profile (issue), гасится в App\Telegraph\WebhookHandler
 * при обработке команды /start (resolve). Единственное место, где живёт
 * формат ключа кеша и TTL — иначе они дублировались бы в двух не связанных
 * друг с другом местах.
 */
final class TelegramLinkCode
{
    private const TTL_MINUTES = 10;

    public static function issue(User $user): string
    {
        $code = Str::random(32);

        Cache::put(self::key($code), $user->id, now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /**
     * pull() = get + forget: код одноразовый, повторный /start с тем же
     * значением параметра больше никого не привяжет.
     */
    public static function resolve(string $code): ?User
    {
        $userId = Cache::pull(self::key($code));

        return $userId ? User::find($userId) : null;
    }

    private static function key(string $code): string
    {
        return "telegram-link:{$code}";
    }
}
