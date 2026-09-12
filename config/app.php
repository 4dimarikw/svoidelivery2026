<?php

return [

    /*
     * Название приложения для уведомлений и элементов интерфейса.
     */

    'name' => env('APP_NAME', 'Laravel'),

    /*
     * Окружение приложения определяет конфигурацию используемых сервисов; задаётся в .env.
     */

    'env' => env('APP_ENV', 'production'),

    /*
     * Режим отладки включает подробные сообщения об ошибках со стеком вызовов.
     */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
     * Корневой URL приложения для генерации ссылок в командах Artisan.
     */

    'url' => env('APP_URL', 'http://localhost'),

    /*
     * Часовой пояс приложения для операций с датой и временем.
     */

    'timezone' => 'Europe/Moscow',

    /*
     * Язык приложения, резервный язык и язык генерируемых тестовых данных.
     */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
     * Ключ шифрования приложения. Используется криптографическими сервисами Laravel.
     */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
     * Драйвер режима обслуживания: file хранит состояние локально, cache позволяет разделять его между
     * серверами.
     */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
