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
     * Настройки Yandex SmartCaptcha для SmartCaptchaRule и компонента x-ui.smart-captcha. client_key — публичный
     * ключ виджета, server_key — ключ проверки на сервере. Капча отключена по умолчанию. Отсутствующий токен
     * отклоняется, включая сбои виджета в браузере; пропуск проверки разрешён только при сбое серверного API.
     */

    'smart_captcha' => [
        'enabled' => (bool) env('SMART_CAPTCHA_ENABLED', false),
        'client_key' => env('SMART_CAPTCHA_CLIENT_KEY'),
        'server_key' => env('SMART_CAPTCHA_SERVER_KEY'),
        'timeout' => (int) env('SMART_CAPTCHA_TIMEOUT', 2),
    ],
];
