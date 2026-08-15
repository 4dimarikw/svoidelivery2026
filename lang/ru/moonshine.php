<?php

// App-owned labels for our custom app/MoonShine/Resources/* classes (field
// labels, resource titles, menu group names). MoonShine's own admin::ui.*
// strings (moonshine_users, roles) stay in the package's own translations —
// this file only covers resources we authored.

return [
    'group' => [
        'catalog' => 'Каталог',
        'users' => 'Пользователи',
        'orders' => 'Заказы',
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

    'category' => [
        'title' => 'Категории',
        'fields' => [
            'code' => 'Код',
            'name' => 'Название',
            'slug' => 'Slug',
            'is_active' => 'Активна',
            'products_count' => 'Товаров',
            'match_rules_count' => 'Правил',
            'expects_container' => 'Ожидает тару',
            'expects_volume' => 'Ожидает объём',
            'price_exempt' => 'Не фильтровать по мин. цене',
            'name_from_article' => 'Имя из артикула',
            'default_brand' => 'Бренд по умолчанию',
            'container_code' => 'Фиксированная тара',
            'match_rules' => 'Правила резолва',
            'properties' => 'Свойства',
            'pivot_is_required' => 'Обязательное',
            'pivot_is_filterable' => 'Фильтруемое',
            'pivot_is_visible' => 'Видимое',
            'pivot_sort_order' => 'Порядок',
        ],
        'tabs' => [
            'main' => 'Основное',
            'import' => 'Импорт',
            'match_rules' => 'Правила резолва',
            'properties' => 'Свойства',
        ],
    ],

    'category_match_rule' => [
        'title' => 'Правила резолва категории',
        'fields' => [
            'type' => 'Тип',
            'match_when' => 'Когда',
            'value' => 'Значение',
            'priority' => 'Приоритет',
            'is_active' => 'Активно',
        ],
    ],

    'catalog_import_settings' => [
        'title' => 'Настройки импорта',
        'saved' => 'Настройки сохранены',
        'fields' => [
            'alcohol_marker' => 'Маркер алкоголя',
            'accessory_marker' => 'Маркер сопутствующих товаров',
            'advent_marker' => 'Маркер адвент-календарей',
            'fallback_slug' => 'Категория по умолчанию',
            'zero_out_missing' => 'Обнулять остаток товаров, пропавших из выгрузки',
            'zero_out_max_percent' => 'Порог обнуления, %',
            'product_status' => 'Статус новых товаров',
            'new_days' => 'Товар считается новинкой, дней',
            'cache' => 'Кэш',
            'extra_charge' => 'Наценка',
            'last_catalog_update' => 'Дата последнего импорта',
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
            'label' => 'Короткое название',
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
            'favorites_count' => 'В избранном',
            'profile' => 'Профиль',
            'addresses' => 'Адреса',
            'favorites' => 'Избранное',
        ],
        'tabs' => [
            'main' => 'Основное',
            'profile' => 'Профиль',
            'addresses' => 'Адреса',
            'favorites' => 'Избранное',
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
            'is_bot_active' => 'Бот активен',
            'last_bot_error' => 'Последняя ошибка бота',
            'bot_checked_at' => 'Проверен',
        ],
    ],

    'address' => [
        'title' => 'Адреса',
        'fields' => [
            'label' => 'Название',
            'city' => 'Город',
            'address' => 'Адрес',
            'comment' => 'Комментарий',
            'is_default' => 'Адрес по умолчанию',
        ],
    ],

    'favorite' => [
        'title' => 'Избранное',
        'fields' => [
            'user' => 'Пользователь',
            'product' => 'Товар',
            'created_at' => 'Добавлено',
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

    'order' => [
        'title' => 'Заказы',
        'fields' => [
            'number' => 'Номер заказа',
            'comment' => 'Комментарий',
            'amount' => 'Сумма',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
            'user' => 'Пользователь',
        ],
    ],

    'order_customer' => [
        'title' => 'Получатели',
        'fields' => [
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'phone' => 'Телефон',
            'city' => 'Город',
            'address' => 'Адрес',
            'comment' => 'Комментарий',
        ],
    ],

    'order_item' => [
        'title' => 'Позиции заказа',
        'fields' => [
            'price' => 'Цена',
            'quantity' => 'Количество',
            'product' => 'Товар',
            'order' => 'Заказ',
        ],
    ],

    'payment_method' => [
        'title' => 'Способы оплаты',
        'fields' => [
            'title' => 'Название',
            'redirect_to_pay' => 'Редирект на оплату',
        ],
    ],

    'delivery_type' => [
        'title' => 'Способы доставки',
        'fields' => [
            'title' => 'Название',
            'price' => 'Стоимость',
            'with_address' => 'Требует адрес',
        ],
    ],
];
