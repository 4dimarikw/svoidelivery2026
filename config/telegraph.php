<?php

use App\Telegraph\WebhookHandler;
use DefStudio\Telegraph\Storage\CacheStorageDriver;
use DefStudio\Telegraph\Storage\FileStorageDriver;
use DefStudio\Telegraph\Telegraph;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;

return [
    /*
     * Базовый URL Telegram API, включая собственный сервер API.
     */
    'telegram_api_url' => 'https://api.telegram.org/',

    /*
     * Режим разметки сообщений Telegraph: html, markdown или MarkdownV2.
     */
    'default_parse_mode' => Telegraph::PARSE_HTML,

    'webhook' => [
        /*
         * Домен вебхука; при null используется app.url.
         */
        'domain' => env('TELEGRAM_WEBHOOK_DOMAIN'),

        /*
         * Путь вебхука на сервере; null отключает маршрут.
         */
        'url' => env('TELEGRAPH_WEBHOOK_URL', '/telegraph/{token}/webhook'),

        /*
         * Обработчик входящего вебхука Telegraph. См. https://docs.defstudio.it/telegraph/webhooks/overview.
         */
        'handler' => WebhookHandler::class,

        /*
         * Промежуточные обработчики маршрута вебхука.
         */
        'middleware' => [],

        /*
         * Домен регистрации вебхука для локального сервера Telegram Bot API. См.
         * https://core.telegram.org/bots/api#using-a-local-bot-api-server.
         */

        /*
         * Регистрация неизвестных команд вебхука как исключений в журнале приложения.
         */
        'report_unknown_commands' => env('TELEGRAPH_REPORT_UNKNOWN_COMMANDS', true),

        /**
         * Секретный токен заголовка X-Telegram-Bot-Api-Secret-Token для проверки подлинности вебхука.
         */
        'secret' => env('TELEGRAPH_WEBHOOK_SECRET'),

        /**
         * Максимальное число одновременных подключений к вебхуку.
         */
        'max_connections' => env('TELEGRAPH_WEBHOOK_MAX_CONNECTIONS', 40),

        /**
         * Типы обновлений вебхука. При null принимаются все стандартные типы, кроме chat_member,
         * message_reaction и message_reaction_count.
         *
         * @see https://core.telegram.org/bots/api#setwebhook
         */
        'allowed_updates' => null,

        /*
         * Запись входящих сообщений вебхука в журнал.
         */
        'debug' => env('TELEGRAPH_WEBHOOK_DEBUG', false),
    ],

    /*
     * Таймаут HTTP-запроса к серверам Telegram.
     */
    'http_timeout' => env('TELEGRAPH_HTTP_TIMEOUT', 30),

    /*
     * HTTP-прокси для запросов к Telegram в формате http://user:password@host:port.
     */
    'http_proxy' => env('TELEGRAPH_HTTP_PROXY', null),

    /*
     * Таймаут установки HTTP-соединения с Telegram.
     */
    'http_connection_timeout' => env('TELEGRAPH_HTTP_CONNECTION_TIMEOUT', 10),

    'security' => [
        /*
         * Разрешение запросов обратного вызова от незарегистрированных чатов.
         */
        'allow_callback_queries_from_unknown_chats' => false,

        /*
         * Разрешает сообщения и команды незарегистрированных чатов, включая первый /start для привязки Telegram
         * в профиле.
         */
        'allow_messages_from_unknown_chats' => true,

        /*
         * if enabled, store unknown chats as new TelegraphChat models
         *
         * Выключено намеренно: App\Telegraph\WebhookHandler::start() сам
         * сохраняет чат — сразу с привязанным user_id. Автосохранение пакета
         * этого не умеет и просто засорило бы telegraph_chats безлинковыми
         * строками от кого угодно, кто напишет боту.
         */
        'store_unknown_chats_in_db' => false,
    ],

    /*
     * Модели бота и чата должны наследовать DefStudio\Telegraph\Models\TelegraphBot и TelegraphChat
     * соответственно.
     */
    'models' => [
        'bot' => TelegramBot::class,
        'chat' => TelegramChat::class,
    ],

    'storage' => [
        /**
         * Драйвер хранения данных Telegraph по умолчанию.
         */
        'default' => 'file',

        'stores' => [
            'file' => [
                /**
                 * Драйвер хранилища, реализующий DefStudio\Telegraph\Contracts\StorageDriver.
                 */
                'driver' => FileStorageDriver::class,

                /*
                 * Диск Laravel из config/filesystems.php; null использует диск по умолчанию.
                 */
                'disk' => 'local',

                /**
                 * Корневой каталог хранения данных Telegraph на диске.
                 */
                'root' => 'telegraph',
            ],
            'cache' => [
                /**
                 * Драйвер хранилища, реализующий DefStudio\Telegraph\Contracts\StorageDriver.
                 */
                'driver' => CacheStorageDriver::class,

                /*
                 * Хранилище кеша Laravel из config/cache.php; null использует хранилище по умолчанию.
                 */
                'store' => null,

                /*
                 * Префикс ключей кеша.
                 */
                'key_prefix' => 'tgph',
            ],
        ],
    ],

    /**
     * Правила проверки вложений с ограничениями Telegram Bot API. Локальный сервер API допускает настройку
     * повышенных лимитов: https://core.telegram.org/bots/api#using-a-local-bot-api-server.
     */
    'attachments' => [
        'thumbnail' => [
            'max_size_kb' => 200,
            'max_height_px' => 320,
            'max_width_px' => 320,
            'allowed_ext' => ['jpg'],
        ],
        'photo' => [
            'max_size_mb' => 10,
            'max_ratio' => 20,
            'height_width_sum_px' => 10000,
        ],
        'animation' => [
            'max_size_mb' => 50,
        ],
        'video' => [
            'max_size_mb' => 50,
        ],
        'audio' => [
            'max_size_mb' => 50,
        ],
        'document' => [
            'max_size_mb' => 50,
        ],
        'sticker' => [
            'max_size_mb' => 50,
        ],
    ],

    /*
     * Настройки команд.
     */
    'commands' => [
        /*
         * Префиксы команд в сообщениях чата; стандартный префикс — /.
         */
        'start_with' => ['/'],
    ],

    'payments' => [
        'provider_token' => env('TELEGRAPH_PAYMENT_PROVIDER_TOKEN', ''),
    ],
];
