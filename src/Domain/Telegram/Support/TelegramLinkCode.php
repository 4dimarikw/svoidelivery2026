<?php

namespace Domain\Telegram\Support;

use Domain\Auth\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Одноразовый код для привязки Telegram к уже авторизованному аккаунту:
 * выдаётся на /account/profile или странице профиля MoonShine (issue/issueFor),
 * гасится в App\Telegraph\WebhookHandler при обработке команды /start
 * (resolve/resolveSubject). Единственное место, где живёт формат ключа кеша
 * и TTL — иначе они дублировались бы в двух не связанных друг с другом
 * местах.
 *
 * Субъект (кого привязывать) хранится вместе с кодом — так один и тот же
 * механизм обслуживает и сайтового User (SUBJECT_USER), и админа MoonShine
 * (SUBJECT_MOONSHINE_USER), не заводя второй параллельный класс с той же
 * логикой TTL/одноразовости.
 */
final class TelegramLinkCode
{
    private const TTL_MINUTES = 10;

    public const SUBJECT_USER = 'user';

    public const SUBJECT_MOONSHINE_USER = 'moonshine_user';

    public static function issue(User $user): string
    {
        return self::issueFor(self::SUBJECT_USER, $user->id);
    }

    public static function issueFor(string $subject, int $id): string
    {
        $code = Str::random(32);

        Cache::put(self::key($code), ['subject' => $subject, 'id' => $id], now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /**
     * pull() = get + forget: код одноразовый, повторный /start с тем же
     * значением параметра больше никого не привяжет.
     */
    public static function resolve(string $code): ?User
    {
        $payload = self::resolveSubject($code);

        if ($payload === null || $payload['subject'] !== self::SUBJECT_USER) {
            return null;
        }

        return User::find($payload['id']);
    }

    /**
     * @return array{subject: string, id: int}|null
     */
    public static function resolveSubject(string $code): ?array
    {
        $value = Cache::pull(self::key($code));

        if ($value === null) {
            return null;
        }

        // Обратная совместимость: код, выпущенный до появления субъектов,
        // хранит голый user_id (int), а не ['subject' => ..., 'id' => ...].
        // TTL всего 10 минут, но выкатка может попасть в это окно.
        if (is_int($value)) {
            return ['subject' => self::SUBJECT_USER, 'id' => $value];
        }

        return $value;
    }

    private static function key(string $code): string
    {
        return "telegram-link:{$code}";
    }
}
