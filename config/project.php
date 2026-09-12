<?php

return [
    'timezone' => 'Europe/Moscow',

    'default_beer_label' => env('APP_URL').'/img/product-placeholder.png',

    // Домен Untappd для нормализации ссылок на пиво при чтении и записи в UntappdBeer.
    'untappd_base_url' => 'https://untappd.com',
];
