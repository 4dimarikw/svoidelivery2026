<?php

use Illuminate\Support\Str;

return [

    /*
     * Драйвер хранения сессий.
     */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
     * Срок бездействия сессии в минутах. expire_on_close завершает сессию при закрытии браузера.
     */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
     * Шифрование данных сессии перед сохранением.
     */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
     * Каталог сессий для драйвера file.
     */

    'files' => storage_path('framework/sessions'),

    /*
     * Подключение хранения сессий для драйверов database и redis.
     */

    'connection' => env('SESSION_CONNECTION'),

    /*
     * Таблица хранения сессий для драйвера database.
     */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
     * Хранилище кеша сессий для драйверов dynamodb, memcached и redis.
     */

    'store' => env('SESSION_STORE'),

    /*
     * Вероятность очистки истёкших сессий при обработке запроса.
     */

    'lottery' => [2, 100],

    /*
     * Имя cookie сессии.
     */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
     * Путь, в пределах которого браузер отправляет cookie сессии.
     */

    'path' => env('SESSION_PATH', '/'),

    /*
     * Домен cookie сессии.
     */

    'domain' => env('SESSION_DOMAIN'),

    /*
     * Передача cookie сессии только по HTTPS.
     */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
     * Ограничивает доступ к cookie протоколом HTTP, исключая чтение из JavaScript.
     */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
     * Политика SameSite для межсайтовых запросов: lax, strict, none или null. См.
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value.
     */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
     * Разделение cookie по сайту верхнего уровня в межсайтовом контексте; требует Secure и SameSite=none.
     */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
