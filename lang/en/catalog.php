<?php

// App-owned catalog page copy (home / route `home`). Not in ui.php — that's
// reserved for strings internal to <x-ui.*>, not page copy. Not in
// auth.php/validation.php/pagination.php — those are managed by laravel-lang
// and get overwritten on `composer post-update-cmd` → `artisan lang:update`.

return [
    'title' => 'Catalog',

    'filters' => [
        'title' => 'Filters',
        'category' => 'Category',
        'manufacturer' => 'Manufacturer',
        'volume' => 'Volume',
        'container' => 'Container',
        'price' => 'Price, ₽',
        'price_min' => 'from',
        'price_max' => 'to',
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

    'found' => 'Found: :count',
    'empty' => 'No products match the selected filters.',
    'new' => 'New',
    'out_of_stock' => 'Out of stock',
    // "Buy" is wired up (Domain\Cart) — turns into a quantity stepper after
    // the first click, see product-card.blade.php. "Notify me" is still
    // decorative — the product is out of stock.
    'buy' => 'Buy',
    'notify' => 'Notify me',
    'add_to_favorites' => 'Add to favorites',
    'remove_from_favorites' => 'Remove from favorites',
    'load_more' => 'Load more',
    'load_error' => 'Failed to load products.',
    'retry' => 'Retry',

    'cart' => [
        // Stepper −/+ button aria-labels (design-system.html, §06).
        'increase' => 'Add one more',
        'decrease' => 'Remove one',
    ],
];
