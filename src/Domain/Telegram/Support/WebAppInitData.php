<?php

namespace Domain\Telegram\Support;

/**
 * Проверка Telegram Mini App initData (window.Telegram.WebApp.initData).
 *
 * Другой алгоритм, чем у Login Widget (SocialiteProviders\Telegram\Provider):
 * там secret = sha256(token), здесь secret = HMAC_SHA256(key: "WebAppData",
 * data: token) — https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app.
 * Поэтому отдельный VO, а не второй вызов Socialite.
 */
final class WebAppInitData
{
    /**
     * initData выдаётся один раз при открытии Mini App и не обновляется,
     * пока она открыта — окно шире, чем у Login Widget
     * (TelegramLoginController::AUTH_DATE_TTL = 60): иначе долго висящее
     * приложение отваливалось бы по протухшей подписи.
     */
    public const AUTH_DATE_TTL = 86400;

    private function __construct(
        public readonly int $id,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $username,
    ) {}

    /**
     * null — initData отсутствует, подпись не совпала или auth_date протух.
     */
    public static function verify(string $initData, string $botToken): ?self
    {
        if ($initData === '') {
            return null;
        }

        parse_str($initData, $params);

        if (! isset($params['hash'], $params['auth_date'], $params['user']) || ! is_string($params['hash'])) {
            return null;
        }

        $authDate = (int) $params['auth_date'];

        if ($authDate <= 0 || now()->timestamp - $authDate > self::AUTH_DATE_TTL) {
            return null;
        }

        $hash = $params['hash'];
        unset($params['hash']);

        $dataCheckString = collect($params)
            ->sortKeys()
            ->map(fn ($value, $key) => "$key=$value")
            ->join("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expectedHash, $hash)) {
            return null;
        }

        $user = json_decode((string) $params['user'], true);

        if (! is_array($user) || ! isset($user['id']) || ! is_numeric($user['id'])) {
            return null;
        }

        return new self(
            id: (int) $user['id'],
            firstName: $user['first_name'] ?? null,
            lastName: $user['last_name'] ?? null,
            username: $user['username'] ?? null,
        );
    }

    /**
     * Тот же приоритет, что у TelegramLoginController::register():
     * "Имя Фамилия" → username → голый id.
     */
    public function displayName(): string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : ($this->username ?? (string) $this->id);
    }
}
