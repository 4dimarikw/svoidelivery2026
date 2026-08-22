<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Honeypot регистрации
    |--------------------------------------------------------------------------
    |
    | Скрытая приманка + поле-таймер на форме регистрации (Infrastructure\Rules\HoneypotRule,
    | <x-ui.honeypot>). Значение таймера шифруется (Crypt::encryptString) — голый
    | unix-timestamp бот подделал бы одной строкой.
    |
    */

    'honeypot' => [
        'enabled' => (bool) env('HONEYPOT_ENABLED', true),
        'field' => env('HONEYPOT_FIELD', 'website_url'),
        'timer_field' => env('HONEYPOT_TIMER_FIELD', 'form_loaded_at'),
        'min_seconds' => (int) env('HONEYPOT_MIN_SECONDS', 3),
        // Слишком старая форма (протухшая вкладка/реплей) тоже отклоняется,
        // но отдельным сообщением — это не признак бота, это признак того,
        // что пользователю надо обновить страницу.
        'max_seconds' => (int) env('HONEYPOT_MAX_SECONDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting регистрации / сброса пароля / повторной отправки письма
    |--------------------------------------------------------------------------
    |
    | Лимитеры регистрируются в FortifyServiceProvider (RateLimiter::for) и
    | подключаются либо через config('fortify.limiters') (verification —
    | штатный ключ Fortify), либо через App\Http\Middleware\ThrottleAuthForms
    | (register/password-reset — Fortify эти маршруты сам не даёт троттлить).
    | По identity (email) и по IP — раздельные лимиты, чтобы один атакующий
    | не обходил защиту сотней разных email с одного хоста.
    |
    */

    'register_throttle' => [
        'per_identity' => (int) env('REGISTER_THROTTLE_PER_IDENTITY', 3),
        'per_ip' => (int) env('REGISTER_THROTTLE_PER_IP', 10),
        'decay_minutes' => (int) env('REGISTER_THROTTLE_DECAY', 10),
    ],

    'password_reset_throttle' => [
        'per_identity' => (int) env('PASSWORD_RESET_THROTTLE_PER_IDENTITY', 3),
        'per_ip' => (int) env('PASSWORD_RESET_THROTTLE_PER_IP', 10),
        'decay_minutes' => (int) env('PASSWORD_RESET_THROTTLE_DECAY', 10),
    ],

    'verification_throttle' => [
        'per_user' => (int) env('VERIFICATION_THROTTLE_PER_USER', 6),
        'decay_minutes' => (int) env('VERIFICATION_THROTTLE_DECAY', 1),
    ],

    // POST /user/confirm-password — подбор пароля уже авторизованным
    // пользователем. Ключ по user_id, не по email — на этой форме email
    // вообще не передаётся.
    'password_confirm_throttle' => [
        'per_user' => (int) env('PASSWORD_CONFIRM_THROTTLE_PER_USER', 5),
        'decay_minutes' => (int) env('PASSWORD_CONFIRM_THROTTLE_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Yandex SmartCaptcha
    |--------------------------------------------------------------------------
    |
    | Infrastructure\Rules\SmartCaptchaRule, <x-ui.smart-captcha>. Ключи
    | smart_captcha.* оставлены как были заведены в самом правиле (enabled/
    | server_key/timeout) — client_key новый, публичный sitekey виджета.
    |
    | Выключена по умолчанию: включать только после того, как в
    | storage/logs/security.log станет видно, что виджет реально грузится у
    | пользователей (browserslist проекта — Safari >= 13.1, Chrome >= 80,
    | виджет может не отрендериться в старом браузере/с блокировщиком —
    | тогда $implicit=true на правиле фейлит валидацию наглухо, никакого
    | fail-open тут нет, fail-open только на стороне сбоя API Яндекса).
    |
    */

    'smart_captcha' => [
        'enabled' => (bool) env('SMART_CAPTCHA_ENABLED', false),
        'client_key' => env('SMART_CAPTCHA_CLIENT_KEY'),
        'server_key' => env('SMART_CAPTCHA_SERVER_KEY'),
        'timeout' => (int) env('SMART_CAPTCHA_TIMEOUT', 2),
    ],
];
