<?php

// App-owned labels for our custom app/MoonShine/Resources/* classes (field
// labels, resource titles, menu group names). MoonShine's own admin::ui.*
// strings (moonshine_users, roles) stay in the package's own translations —
// this file only covers resources we authored.

return [
    'group' => [
        'catalog' => 'Каталог',
    ],

    'product' => [
        'title' => 'Товары',
        'fields' => [
            'image' => 'Изображение',
            'article' => 'Артикул',
            'name' => 'Название',
            'slug' => 'Slug',
            'category' => 'Категория',
            'manufacturer' => 'Производитель',
            'volume' => 'Объём',
            'container' => 'Тара',
            'price' => 'Цена',
            'stock_quantity' => 'Остаток',
            'in_stock' => 'В наличии',
            'status' => 'Статус',
            'external_code' => 'Внешний код',
            'description' => 'Описание',
            'synced_at' => 'Синхронизирован',
            'brand' => 'Бренд',
            'package_units' => 'Штук в упаковке',
            'packaging_raw' => 'Упаковка (как в 1С)',
            'shelf_life_days' => 'Срок годности, дней',
            'sales_rating' => 'Рейтинг продаж',
            'source_uuid' => 'UUID источника',
            'main_image' => 'Загрузить изображение',
            'current_image' => 'Текущее изображение',
            'beer_details' => 'Пивные характеристики',
            'beer_style' => 'Стиль',
            'abv' => 'Крепость, %',
            'ibu' => 'IBU',
            'plato' => 'Плотность, °P',
            'ebc' => 'Цвет, EBC',
            'barcodes' => 'Штрихкоды',
        ],
        'tabs' => [
            'main' => 'Основное',
            'classification' => 'Классификация',
            'price' => 'Цена и остаток',
            'extra' => 'Дополнительно',
            'image' => 'Изображение',
            'beer' => 'Пиво',
            'barcodes' => 'Штрихкоды',
        ],
    ],

    'product_barcode' => [
        'title' => 'Штрихкоды',
        'fields' => [
            'barcode' => 'Штрихкод',
            'product' => 'Товар',
        ],
    ],

    'beer_style' => [
        'title' => 'Стили пива',
        'fields' => [
            'name' => 'Название',
            'parent' => 'Родительский стиль',
            'slug' => 'Slug',
            'is_active' => 'Активен',
            'products_count' => 'Товаров',
        ],
    ],

    'property' => [
        'title' => 'Свойства',
        'fields' => [
            'code' => 'Код',
            'name' => 'Название',
            'unit' => 'Ед. изм.',
        ],
        'index' => [
            'type' => 'Тип',
            'storage_table' => 'Таблица',
            'storage_column' => 'Колонка',
        ],
        'form' => [
            'data_type' => 'Тип данных',
            'storage_table' => 'Таблица хранения',
            'storage_column' => 'Колонка хранения',
        ],
    ],

    'container' => [
        'title' => 'Тара',
        'fields' => [
            'code' => 'Код',
            'name' => 'Название',
            'is_active' => 'Активна',
            'products_count' => 'Товаров',
        ],
    ],

    'manufacturer' => [
        'title' => 'Производители',
        'fields' => [
            'name' => 'Название',
            'slug' => 'Slug',
            'is_active' => 'Активен',
            'products_count' => 'Товаров',
        ],
    ],

    'volume' => [
        'title' => 'Объёмы',
        'fields' => [
            'milliliters' => 'Объём, мл',
            'label' => 'Название',
            'products_count' => 'Товаров',
        ],
    ],
];
