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
        public readonly int $authDate,
        public readonly string $signature,
    ) {}

    /**
     * Возвращает null при отсутствии initData, неверной подписи, истёкшем auth_date или ошибке данных
     * пользователя. Выходной параметр reason позволяет TelegramLoginController различать причины отказа.
     */
    public static function verify(mixed $initData, string $botToken, ?string &$reason = null): ?self
    {
        $reason = null;

        if (! is_string($initData) || $initData === '' || strlen($initData) > 16384) {
            $reason = 'empty_init_data';

            return null;
        }

        // Не допускаем массивы, повторные ключи и нормализацию имён parse_str.
        $params = [];
        foreach (explode('&', $initData) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2 || preg_match('/%(?![a-fA-F0-9]{2})/', $pair)) {
                $reason = 'invalid_fields';

                return null;
            }
            $key = urldecode($parts[0]);
            if (array_key_exists($key, $params)) {
                $reason = 'invalid_fields';

                return null;
            }
            $params[$key] = urldecode($parts[1]);
        }
        if (! TelegramAuthFields::scalarFields($params)) {
            $reason = 'invalid_fields';

            return null;
        }

        if (! isset($params['hash'], $params['auth_date'], $params['user']) || ! is_string($params['hash'])) {
            $reason = 'missing_fields';

            return null;
        }

        $reason = TelegramAuthFields::dateFailure($params['auth_date'], self::AUTH_DATE_TTL);
        if ($reason !== null) {
            return null;
        }
        $authDate = (int) $params['auth_date'];

        $hash = $params['hash'];
        unset($params['hash']);

        $dataCheckString = collect($params)
            ->sortKeys()
            ->map(fn ($value, $key) => "$key=$value")
            ->join("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! preg_match('/\A[a-f0-9]{64}\z/D', $hash) || ! hash_equals($expectedHash, $hash)) {
            $reason = 'hash_mismatch';

            return null;
        }

        $user = json_decode($params['user'], true);

        if (! is_array($user) || ! isset($user['id']) || ! is_int($user['id']) || $user['id'] <= 0) {
            $reason = 'invalid_user_payload';

            return null;
        }
        foreach (['first_name', 'last_name', 'username'] as $field) {
            if (array_key_exists($field, $user) && ! is_string($user[$field])) {
                $reason = 'invalid_user_payload';

                return null;
            }
        }

        return new self(
            id: (int) $user['id'],
            firstName: $user['first_name'] ?? null,
            lastName: $user['last_name'] ?? null,
            username: $user['username'] ?? null,
            authDate: $authDate,
            signature: $expectedHash,
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
