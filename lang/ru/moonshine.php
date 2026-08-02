<?php

// App-owned labels for our custom app/MoonShine/Resources/* classes (field
// labels, resource titles, menu group names). MoonShine's own admin::ui.*
// strings (moonshine_users, roles) stay in the package's own translations —
// this file only covers resources we authored.

return [
    'group' => [
        'catalog' => 'Каталог',
        'users' => 'Пользователи',
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
            'brand' => 'Бренд',
            'package_units' => 'Штук в упаковке',
            'packaging_raw' => 'Упаковка (как в 1С)',
            'shelf_life_days' => 'Срок годности, дней',
            'flags' => 'Опции',
            'main_image' => 'Загрузить изображение',
            'current_image' => 'Текущее изображение',
            'beer_details' => 'Пивные характеристики',
            'beer_style' => 'Стиль',
            'abv' => 'Крепость, %',
            'ibu' => 'IBU',
            'plato' => 'Плотность, °P',
            'ebc' => 'Цвет, EBC',
        ],
        'tabs' => [
            'main' => 'Основное',
            'classification' => 'Классификация',
            'price' => 'Цена и остаток',
            'extra' => 'Дополнительно',
            'image' => 'Изображение',
            'beer' => 'Пиво',
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

    'user' => [
        'title' => 'Пользователи',
        'fields' => [
            'name' => 'Имя',
            'email' => 'E-mail',
            'email_verified_at' => 'Почта подтверждена',
            'password' => 'Пароль',
            'password_confirmation' => 'Повтор пароля',
            'change_password' => 'Сменить пароль',
            'phone' => 'Телефон',
            'created_at' => 'Дата регистрации',
            'addresses_count' => 'Адресов',
            'profile' => 'Профиль',
            'addresses' => 'Адреса',
        ],
        'tabs' => [
            'main' => 'Основное',
            'profile' => 'Профиль',
            'addresses' => 'Адреса',
        ],
    ],

    'profile' => [
        'title' => 'Профили',
        'fields' => [
            'full_name' => 'ФИО',
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'patronymic' => 'Отчество',
            'phone' => 'Телефон',
            'vk_url' => 'Ссылка VK',
            'telegram_url' => 'Ссылка Telegram',
            'default_order_comment' => 'Комментарий к заказу по умолчанию',
        ],
    ],

    'address' => [
        'title' => 'Адреса',
        'fields' => [
            'label' => 'Название',
            'city' => 'Город',
            'street' => 'Улица',
            'house' => 'Дом',
            'apartment' => 'Квартира',
            'entrance' => 'Подъезд',
            'floor' => 'Этаж',
            'intercom' => 'Домофон',
            'comment' => 'Комментарий',
            'is_default' => 'Адрес по умолчанию',
        ],
    ],

    'untappd_beer' => [
        'title' => 'Untappd',
        'fields' => [
            'beer_id' => 'Untappd ID',
            'name' => 'Название',
            'brewery' => 'Пивоварня',
            'style' => 'Стиль',
            'description' => 'Описание',
            'rating_count' => 'Оценок',
            'rating_score' => 'Рейтинг',
            'label' => 'Изображение',
            'url' => 'URL',
            'synced_at' => 'Синхронизировано',
            'products_count' => 'Товаров',
        ],
        'actions' => [
            'resync' => 'Обновить из Untappd',
        ],
        'query_tags' => [
            'not_synced' => 'Не синхронизированы',
            'no_products' => 'Без привязанных товаров',
        ],
        'toasts' => [
            'resync_success' => 'Данные обновлены из Untappd',
            'resync_failed' => 'Не удалось получить данные из Untappd',
        ],
    ],
];
