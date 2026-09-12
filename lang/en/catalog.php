<?php

// Тексты страницы каталога. ui.php содержит тексты компонентов x-ui; файлы auth.php, validation.php и
// pagination.php управляются laravel-lang и перезаписываются lang:update.

return [
    'title' => 'Catalog',

    'filters' => [
        'title' => 'Filters',
        'category' => 'Category',
        'manufacturer' => 'Manufacturer',
        'volume' => 'Volume',
        'container' => 'Container',
        'price' => 'Price, ₽',
        // Общие подписи диапазонов цены, крепости и IBU; см. шаблоны фильтров *-range.blade.php.
        'range_min' => 'from',
        'range_max' => 'to',
        'in_stock' => 'In stock only',
        'search' => 'Product name',
        'apply' => 'Apply',
        'reset' => 'Reset',
        'toggle' => 'Filters',
        'close' => 'Close filters',
    ],

    'spec' => [
        'ibu' => 'IBU',
        'plato' => '°P',
        'ebc' => 'EBC',
    ],

    'no_image' => 'Product image unavailable',

    'breadcrumbs' => 'Breadcrumbs',
    'go_to_product' => 'Go to product page',

    'found' => 'Found: :count',
    'empty' => 'No products match the selected filters.',
    'new' => 'New',
    'out_of_stock' => 'Out of stock',
    // Кнопка покупки после добавления товара показывает изменение количества. При отсутствии товара кнопка
    // отключена и сохраняет текст.
    'buy' => 'Buy',
    'add_to_favorites' => 'Add to favorites',
    'remove_from_favorites' => 'Remove from favorites',
    'load_more' => 'Load more',
    'load_error' => 'Failed to load products.',
    'retry' => 'Retry',

    'cart' => [
        // Доступные подписи кнопок уменьшения и увеличения количества; см. design-system.html §06.
        'increase' => 'Add one more',
        'decrease' => 'Remove one',
        // Сообщение отказа CartController::rejectUnavailable(), если доступность товара изменилась после
        // отображения страницы.
        'unavailable' => 'This item is out of stock and cannot be ordered.',
    ],

    // Страница товара: маршрут product.show, шаблон pages/product.blade.php.
    'product' => [
        'specs' => 'Specifications',
        'description' => 'Description',
        'similar' => 'Similar products',
        'untappd_link' => 'View on Untappd',
        'rating_count' => 'Ratings: :count',

        'spec_labels' => [
            'abv' => 'ABV',
            'ibu' => 'Bitterness',
            'plato' => 'Gravity',
            'ebc' => 'Color',
            'volume' => 'Volume',
            'container' => 'Container',
            'category' => 'Category',
            'manufacturer' => 'Manufacturer',
            'style' => 'Style',
        ],
    ],
];
