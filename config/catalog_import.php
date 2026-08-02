<?php

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
        'бут' => 'piece',
        'пачка' => 'pack',
        'газ. баллон' => 'gas_cylinder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories — единый реестр (свойства + правила резолва slug)
    |--------------------------------------------------------------------------
    | Источник правды для CategorySeeder, ResolveCategoryStage (авто-создание),
    | CategorySlugResolver (резолв slug из сырых колонок CSV) и всех стейджей,
    | зависящих от категории (container/volume/price_exempt/brand).
    |
    | Ключи свойств (все опциональны, дефолт — false/null):
    |   name            — отображаемое имя (CategorySeeder, авто-создание).
    |   sort_order       — порядок в CategorySeeder / авто-создании.
    |   container        — ожидает Container (ResolveContainerStage warning).
    |   volume           — ожидает Volume (ResolveVolumeStage warning).
    |   price_exempt     — не попадает под normalize.min_price (NormalizeRowStage).
    |   default_brand    — бренд для пустого Производитель (ResolveBrandStage).
    |   name_from_article — пустая Марка → имя из Артикул (ResolveProductIdentityStage).
    |   container_code   — фиксированный код тары независимо от Упаковки (ResolveContainerStage).
    |
    | match[] — правила резолва slug, интерпретируются CategorySlugResolver
    | в фиксированном порядке приоритета типов (см. класс):
    |   1. type=alcohol   — верхний сегмент Категория == category_resolution.alcohol_marker.
    |      when: advent (Категория содержит advent_marker) → no_abv (ABV пуст) →
    |            style (keyword в СтильПива) → default (ничего не подошло).
    |   2. type=contains  — Категория (вся строка) содержит needle (case-insensitive).
    |      Порядок вычисления = порядок объявления категорий ниже (probes ДО equipment,
    |      т.к. реальный CSV-формат "Сопутствующие товары>Оборудование").
    |   3. type=accessory_title — верхний сегмент == category_resolution.accessory_marker,
    |      keyword ищется в Наименование (леворасположенное совпадение побеждает,
    |      как в едином regex по всем keywords сразу).
    |   4. Ничего не подошло → category_resolution.fallback.
    */
    'categories' => [
        'beer' => [
            'name' => 'Пиво', 'sort_order' => 1,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'alcohol', 'when' => 'default']],
        ],
        'mead' => [
            'name' => 'Мёд', 'sort_order' => 2,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'alcohol', 'when' => 'style', 'keyword' => 'mead']],
        ],
        'cider' => [
            'name' => 'Сидр', 'sort_order' => 3,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'alcohol', 'when' => 'style', 'keyword' => 'cider']],
        ],
        'non-alcoholic' => [
            'name' => 'Безалкогольные напитки', 'sort_order' => 4,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'alcohol', 'when' => 'no_abv']],
        ],
        'sauce' => [
            'name' => 'Соус', 'sort_order' => 5,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'alcohol', 'when' => 'style', 'keyword' => 'sauce']],
        ],
        'not-defined' => [
            'name' => 'не определена', 'sort_order' => 6,
            // Нет match[] — категория служит catch-all (category_resolution.fallback).
        ],
        'pet-tare-packages' => [
            'name' => 'ПЭТ ТАРА ПАКЕТЫ', 'sort_order' => 7,
            'container' => true, 'volume' => true,
            'container_code' => 'pet',
            'match' => [['type' => 'accessory_title', 'keywords' => ['пэт', 'тара', 'пакет']]],
        ],
        'for-beer' => [
            'name' => 'К пиву', 'sort_order' => 8,
            'match' => [['type' => 'accessory_title', 'keywords' => ['арахис', 'снэки', 'чипсы']]],
        ],
        'clothes' => [
            'name' => 'Одежда', 'sort_order' => 9,
            'match' => [['type' => 'accessory_title', 'keywords' => ['футболка', 'толстовка', 'шапка']]],
        ],
        'attributes' => [
            'name' => 'Атрибутика', 'sort_order' => 10,
            'match' => [['type' => 'accessory_title', 'keywords' => ['шеврон', 'маска', 'флаг', 'атрибутика', 'коврик']]],
        ],
        'souvenirs' => [
            'name' => 'Сувениры', 'sort_order' => 11,
            'match' => [
                ['type' => 'alcohol', 'when' => 'advent'],
                ['type' => 'accessory_title', 'keywords' => ['адвент']],
            ],
        ],
        // probes ДО equipment: обе — type=contains, порядок объявления = порядок проверки.
        'probes' => [
            'name' => 'Пробники', 'sort_order' => 12,
            'container' => true, 'volume' => true,
            'match' => [['type' => 'contains', 'needle' => 'пробники']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | category_resolution — общие маркеры сегментов "Категория", не привязанные
    | к конкретной категории (используются несколькими match-правилами выше).
    |--------------------------------------------------------------------------
    */
    'category_resolution' => [
        'alcohol_marker' => 'Алкогольная продукция',
        'accessory_marker' => 'Сопутствующие товары',
        'advent_marker' => 'адвент',
        'fallback' => 'not-defined',
    ],

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
    | featured_marker → is_featured = true (Акция)
    | is_new вычисляется в PersistVariationStage по категории и возрасту вариации (см. new_flag).
    */
    'flags' => [
        'featured_marker' => 'Акция',
    ],
];
