<?php

use Services\CatalogImport\Stages\DetectVolumeMismatchStage;
use Services\CatalogImport\Stages\FilterCategoryStage;
use Services\CatalogImport\Stages\NormalizeRowStage;
use Services\CatalogImport\Stages\PersistBeerDetailsStage;
use Services\CatalogImport\Stages\PersistProductImageStage;
use Services\CatalogImport\Stages\PersistProductStage;
use Services\CatalogImport\Stages\ResolveAbvStage;
use Services\CatalogImport\Stages\ResolveBeerStyleStage;
use Services\CatalogImport\Stages\ResolveBrandStage;
use Services\CatalogImport\Stages\ResolveCategoryStage;
use Services\CatalogImport\Stages\ResolveContainerStage;
use Services\CatalogImport\Stages\ResolveDescriptionStage;
use Services\CatalogImport\Stages\ResolveEbcStage;
use Services\CatalogImport\Stages\ResolveExternalIdsStage;
use Services\CatalogImport\Stages\ResolveFlagsStage;
use Services\CatalogImport\Stages\ResolveIbuStage;
use Services\CatalogImport\Stages\ResolvePlatoStage;
use Services\CatalogImport\Stages\ResolvePriceStage;
use Services\CatalogImport\Stages\ResolveProductIdentityStage;
use Services\CatalogImport\Stages\ResolveShelfLifeStage;
use Services\CatalogImport\Stages\ResolveVolumeStage;

return [
    /*
    |--------------------------------------------------------------------------
    | Download CSV Paths
    |--------------------------------------------------------------------------
    */

    'download_dir' => 'app/private/catalog/',
    'base_ftp_file' => env('DB_1C_FTP_BASE_FILE'),

    /*
    |--------------------------------------------------------------------------
    | Report log
    |--------------------------------------------------------------------------
    | Каталог для текстовых отчётов: один файл на запуск catalog:import.
    | Путь относительно storage_path(). Лежит внутри storage/logs — покрыт
    | storage/logs/.gitignore ("*"), в репозиторий не попадёт.
    */

    'report_log_dir' => 'logs/catalog-import/',

    /*
    |--------------------------------------------------------------------------
    | Pipeline stages
    |--------------------------------------------------------------------------
    | Each stage is a class implementing ImportStage. Reorder, add, or replace
    | individual stages to change how a specific attribute is resolved.
    |
    | ResolveCategoryStage runs FIRST so that $ctx->category is available to
    | NormalizeRowStage (price-exempt check) and ResolveBrandStage (per-category
    | default_brand). It must also run before ResolveBeerStyleStage.
    | ResolveBeerStyleStage also syncs Untappd data (via Untappd facade)
    | and absorbs the former ResolveUntappdRefStage logic.
    */
    'stages' => [
        ResolveCategoryStage::class,
        FilterCategoryStage::class,
        NormalizeRowStage::class,
        ResolveBrandStage::class,
        ResolveBeerStyleStage::class,
        ResolveContainerStage::class,
        ResolveVolumeStage::class,
        DetectVolumeMismatchStage::class,
        ResolveProductIdentityStage::class,
        ResolveAbvStage::class,
        ResolveIbuStage::class,
        ResolvePlatoStage::class,
        ResolveEbcStage::class,
        ResolveShelfLifeStage::class,
        ResolvePriceStage::class,
        ResolveExternalIdsStage::class,
        ResolveFlagsStage::class,
        ResolveDescriptionStage::class,
        PersistProductStage::class,
        PersistBeerDetailsStage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-commit pipeline stages
    |--------------------------------------------------------------------------
    | Эти стейджи запускаются ВНЕ транзакции строки/чанка, после успешного
    | коммита. Используется для side effects (загрузка изображений, внешние
    | вызовы), которые нельзя откатить через DB::rollBack.
    | В --dry-run эти стейджи пропускаются полностью.
    */
    'post_commit_stages' => [
        PersistProductImageStage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction mode
    |--------------------------------------------------------------------------
    | 'row'   — одна транзакция на строку (по умолчанию; атомарность product+variation).
    | 'chunk' — одна транзакция на chunk_size строк (быстрее на больших файлах, но теряет per-row атомарность).
    | 'none'  — без транзакций (опасно; только для отладки).
    */
    'transaction_mode' => 'row',
    'chunk_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Warning memory limit
    |--------------------------------------------------------------------------
    | Максимальное число warnings, хранимых в памяти и пишущихся в JSON события.
    | warningsTotal в отчёте растёт без ограничений.
    */
    'warning_limit' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Container type map
    |--------------------------------------------------------------------------
    | Substring found in "Упаковка"  → container_types.code.
    | Checked in order; first match wins.
    */
    'container_map' => [
        'пэт кег' => 'pet_keg',
        'пэт' => 'pet',
        'ж/б' => 'can',
        'ст. бут.' => 'glass_bottle',
        'конс./б' => 'tin_can',
        'бут' => 'glass_bottle',
        'пачка' => 'pack',
        'газ. баллон' => 'gas_cylinder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories — единый реестр (свойства + правила резолва slug)
    |--------------------------------------------------------------------------
    | Переехал в БД: таблицы `categories` (флаги expects_container/
    | expects_volume/price_exempt/name_from_article/default_brand/
    | container_code) и `category_match_rules` (правила резолва slug,
    | приоритет — колонка `priority`), плюс глобальные маркеры сегментов
    | (alcohol_marker/accessory_marker/advent_marker/fallback_slug) в
    | Infrastructure\Settings\CatalogImportSettings. Управляется из MoonShine
    | (раздел «Категории»). Единственная точка чтения — CategoryRegistry;
    | CategorySlugResolver и Resolve*-стейджи по-прежнему обращаются только
    | к ней и не знают, что источник сменился.
    */

    /*
    |--------------------------------------------------------------------------
    | NormalizeRowStage — фильтрация строк при загрузке
    |--------------------------------------------------------------------------
    | min_price — строки с ценой ниже этого порога пропускаются
    | (кроме категорий с categories.*.price_exempt = true).
    | excluded_categories — строки с такой категорией (Str::contains) пропускаются.
    */
    'normalize' => [
        'min_price' => 2,
        'excluded_categories' => ['Архив', 'Завод Сырье', 'ПЭТ ТАРА ПАКЕТЫ'],
        'excluded_packages' => ['пэт кег', '0,75', '0.75', '1,5', '1.5'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Имена CSV-колонок экспорта 1С
    |--------------------------------------------------------------------------
    | Меняются только при смене формата выгрузки из 1С.
    | Ключ — семантическое имя (англ.), значение — заголовок из CSV-файла.
    | В комментарии — stage(-ы), читающие колонку.
    */
    'columns' => [
        'object_id' => 'id_объекта',    // NormalizeRowStage, ResolveExternalIdsStage
        'product_code' => 'КодТовара',     // NormalizeRowStage, ResolveExternalIdsStage
        'article' => 'Артикул',       // NormalizeRowStage
        'price' => 'Цена',          // NormalizeRowStage, ResolvePriceStage
        'category' => 'Категория',     // NormalizeRowStage, ResolveCategoryStage
        'manufacturer' => 'Производитель', // ResolveBrandStage
        'abv' => 'ABV',           // ResolveCategoryStage, ResolveAbvStage
        'beer_style' => 'СтильПива',     // ResolveCategoryStage, ResolveBeerStyleStage
        'name_full' => 'Наименование',  // ResolveCategoryStage, ResolveAbvStage, ResolveProductIdentityStage
        'untappd_ref' => 'UntappdRef',    // ResolveBeerStyleStage
        'package' => 'Упаковка',      // ResolveContainerStage, ResolveVolumeStage, ResolveExternalIdsStage
        'brand' => 'Марка',         // ResolveProductIdentityStage
        'product' => 'Товар',         // ResolveProductIdentityStage
        'ibu' => 'IBU',           // ResolveIbuStage
        'plato' => 'Plato',         // ResolvePlatoStage
        'ebc' => 'EBC',           // ResolveEbcStage
        'shelf_life' => 'СрокГодности',  // ResolveShelfLifeStage
        'stock' => 'Остаток',       // ResolvePriceStage
        'sales_rating' => 'РейтингПродаж', // ResolveFlagsStage
        'description' => 'Описание',      // ResolveDescriptionStage
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV file settings
    |--------------------------------------------------------------------------
    */
    'encoding' => 'Windows-1251',
    'delimiter' => ';',

    /*
    |--------------------------------------------------------------------------
    | PersistProductImageStage — загрузка обложки с Untappd
    |--------------------------------------------------------------------------
    | collection — медиа-коллекция Product для обложки (должна быть singleFile).
    */
    'untappd_image' => [
        'collection' => 'main',
    ],

    /*
    |--------------------------------------------------------------------------
    | ResolveFlagsStage — маркеры статуса из колонки РейтингПродаж
    |--------------------------------------------------------------------------
    |
    | promo_marker → products.flags.promo
    */
    'flags' => [
        'promo_marker' => 'Акция',

        // Стартовый набор products.flags (json) для ResolveFlagsStage; ключи —
        // контракт пайплайна и полей формы товара (см. ProductFlagsManager).
        // wu — without_untappd, fil — first_in_list, mss — manual_stock_status.
        // wu/mss/promo стейдж всё равно вычисляет сам, отсюда берётся только
        // состав ключей и дефолт fil.
        'defaults' => [
            'wu' => false,
            'fil' => false,
            'mss' => false,
            'promo' => false,
        ],
    ],
];
