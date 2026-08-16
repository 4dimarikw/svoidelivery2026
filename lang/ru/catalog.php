<?php

// App-owned catalog page copy (главная / route `home`). Не в ui.php — та
// зарезервирована под строки, внутренние для <x-ui.*>, а не под текст страницы.
// Не в auth.php/validation.php/pagination.php — те управляются laravel-lang
// и перезаписываются на `composer post-update-cmd` → `artisan lang:update`.

return [
    'title' => 'Каталог',

    'filters' => [
        'title' => 'Фильтры',
        'category' => 'Категория',
        'manufacturer' => 'Производитель',
        'volume' => 'Объём',
        'container' => 'Тара',
        'price' => 'Цена, ₽',
        // Общие — переиспользуются фильтрами цены/ABV/IBU, не только ценой,
        // несмотря на имя (см. pages/catalog/filters/*-range.blade.php).
        'range_min' => 'от',
        'range_max' => 'до',
        'in_stock' => 'Только в наличии',
        'search' => 'Название товара',
        'apply' => 'Применить',
        'reset' => 'Сбросить',
        'toggle' => 'Фильтры',
        'close' => 'Закрыть фильтры',
    ],

    'spec' => [
        'ibu' => 'IBU',
        'plato' => '°P',
        'ebc' => 'EBC',
    ],

    'no_image' => 'Изображение товара отсутствует',

    'breadcrumbs' => 'Хлебные крошки',
    'go_to_product' => 'Перейти на страницу товара',

    'found' => 'Найдено: :count',
    'empty' => 'По заданным фильтрам ничего не найдено.',
    'new' => 'Новинка',
    'out_of_stock' => 'Нет в наличии',
    // «Купить» — рабочая кнопка (Domain\Cart), превращается в степпер
    // количества после первого клика, см. product-card.blade.php.
    // «Сообщить» — по-прежнему декоративная, товар не в наличии.
    'buy' => 'Купить',
    'notify' => 'Сообщить',
    'add_to_favorites' => 'Добавить в избранное',
    'remove_from_favorites' => 'Убрать из избранного',
    'load_more' => 'Загрузить ещё',
    'load_error' => 'Не удалось загрузить товары.',
    'retry' => 'Повторить',

    'cart' => [
        // aria-label кнопок степпера −/+ (design-system.html, §06).
        'increase' => 'Добавить ещё',
        'decrease' => 'Убрать одну',
        // CartController::rejectUnavailable() — товар кончился уже после
        // рендера страницы (in_stock/stock_quantity разошлись), клампинг в
        // CartManager не дал ничего добавить.
        'unavailable' => 'Товар закончился и недоступен для заказа.',
    ],

    // Страница товара (route `product.show`, pages/product.blade.php).
    'product' => [
        'specs' => 'Характеристики',
        'description' => 'Описание',
        'similar' => 'Похожие товары',
        'untappd_link' => 'Смотреть на Untappd',
        'rating_count' => 'Оценок: :count',

        'spec_labels' => [
            'abv' => 'Крепость',
            'ibu' => 'Горечь',
            'plato' => 'Плотность',
            'ebc' => 'Цвет',
            'volume' => 'Объём',
            'container' => 'Тара',
            'category' => 'Категория',
            'manufacturer' => 'Производитель',
            'style' => 'Стиль',
        ],
    ],
];
