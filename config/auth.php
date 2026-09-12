<?php

use Domain\Auth\Models\User;

return [

    /*
     * Механизм аутентификации и обработчик сброса пароля по умолчанию.
     */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
     * Механизмы аутентификации используют провайдеры пользователей. Драйвер session хранит состояние входа в
     * сессии.
     */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
     * Провайдеры определяют получение пользователей из моделей Eloquent или таблиц базы данных.
     */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

    ],

    /*
     * Настройки хранения токенов сброса пароля. expire задаёт срок действия в минутах, throttle — интервал между
     * выдачей токенов в секундах.
     */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
     * Срок действия подтверждения пароля в секундах.
     */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
