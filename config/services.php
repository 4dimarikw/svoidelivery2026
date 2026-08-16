<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bcraftfest' => [
        'base_url' => env('BCRAFTFEST_API_URL', 'https://api.bcraftfest.ru/api/v1'),
        'db' => env('BCRAFTFEST_DB', 1),
    ],

    /*
     * Telegram Login Widget (socialiteproviders/telegram).
     *
     * Драйвер не ходит по OAuth — getAuthUrl()/getTokenUrl() возвращают null.
     * Виджет сам редиректит браузер на data-auth-url с GET-параметрами, а
     * Provider::user() проверяет их HMAC на ключе hash('sha256', bot_token).
     *
     * Значения НЕ читаются из .env — токен бота живёт в БД (telegraph_bots,
     * см. Domain\Telegram\Models\TelegramBot), это её и есть единственный
     * источник правды (заводится штатной `php artisan telegraph:new-bot`).
     * app/Providers/AppServiceProvider::boot() перезаписывает этот массив на
     * каждый запрос значениями активного бота (кеш-бэкед, дёшево). Пока бот
     * не заведён — остаётся null, и <x-ui.telegram-login-button> прячет
     * кнопку по тому же null.
     *
     * client_id/redirect формально обязательны для
     * SocialiteProviders\Manager\Helpers\ConfigRetriever (иначе
     * MissingConfigException), самим драйвером не используются.
     */
    'telegram' => [
        'bot' => null,
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ],

    'catalog_1c_ftp' => [
        'host' => env('DB_1C_FTP_SERVER'),
        'port' => (int) env('DB_1C_FTP_PORT', 21),
        'username' => env('DB_1C_FTP_LOGIN'),
        'password' => env('DB_1C_FTP_PASSWORD'),
    ],

    'vk' => [
        'access_token' => env('VK_ACCESS_TOKEN'),
        'api_version' => env('VK_API_VERSION', '5.199'),
        'image_hosts' => ['userapi.com', 'vk.com', 'vk-cdn.net', 'vkuserphoto.ru'],
    ],

    /*
     * Уведомления в служебную Telegram-группу (Domain\Telegram\Actions\SendTelegramMessage).
     * Не путать с 'telegram' выше — тот блок про Login Widget и
     * перезаписывается в рантайме из БД, к рассылке в группу отношения не
     * имеет.
     */
    'telegram_notify' => [
        'manage_group' => env('TELEGRAM_MANAGE_GROUP'),
        'new_order_thread_id' => env('TELEGRAM_BOT_NEW_ORDER_THREAD_ID') !== null
            ? (int) env('TELEGRAM_BOT_NEW_ORDER_THREAD_ID')
            : null,
    ],

];
