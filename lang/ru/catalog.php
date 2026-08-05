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
        'price_min' => 'от',
        'price_max' => 'до',
        'in_stock' => 'Только в наличии',
        'search' => 'Название товара',
        'apply' => 'Применить',
        'reset' => 'Сбросить',
        'toggle' => 'Фильтры',
        'close' => 'Закрыть фильтры',
    ],

    'found' => 'Найдено: :count',
    'empty' => 'По заданным фильтрам ничего не найдено.',
    'new' => 'Новинка',
    'out_of_stock' => 'Нет в наличии',
    'load_more' => 'Загрузить ещё',
    'load_error' => 'Не удалось загрузить товары.',
    'retry' => 'Повторить',
];
