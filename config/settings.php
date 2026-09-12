<?php

use Infrastructure\Settings\CatalogImportSettings;
use Infrastructure\Settings\EventLoggingSettings;
use Infrastructure\Settings\SiteSettings;
use Infrastructure\Settings\VKSyncSettings;
use Spatie\LaravelSettings\SettingsCasts\DataCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository;

return [

    /*
     * Зарегистрированные классы настроек приложения.
     */
    'settings' => [
        VKSyncSettings::class,
        CatalogImportSettings::class,
        // Регистрация SiteSettings обеспечивает один экземпляр настроек в области текущего запроса.
        SiteSettings::class,
        EventLoggingSettings::class,
    ],

    /*
     * Каталог создания классов настроек.
     */
    'setting_class_path' => app_path('Settings'),

    /*
     * Каталоги миграций настроек. Команда make:settings-migration использует первый путь, если другой не указан
     * явно.
     */
    'migrations_paths' => [
        database_path('settings'),
    ],

    /*
     * Репозиторий настроек по умолчанию.
     */
    'default_repository' => 'database',

    /*
     * Репозитории хранения и загрузки настроек.
     */
    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
        'redis' => [
            'type' => RedisSettingsRepository::class,
            'connection' => null,
            'prefix' => null,
        ],
    ],

    /*
     * Кодировщик и декодировщик значений; по умолчанию используются json_encode и json_decode.
     */
    'encoder' => null,
    'decoder' => null,

    /*
     * Кеш значений настроек в хранилище Laravel с отдельным префиксом.
     */
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', false),
        'store' => 'file',
        'prefix' => null,
        'ttl' => null,
    ],

    /*
     * Глобальные преобразования свойств настроек со сложными типами.
     */
    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
        Data::class => DataCast::class,
    ],

    /*
     * Каталоги автоматического обнаружения классов настроек.
     */
    'auto_discover_settings' => [
        app_path('Settings'),
    ],

    /*
     * Кеш обнаруженных классов исключает поиск при каждом запуске приложения.
     */
    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
