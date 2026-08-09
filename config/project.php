<?php

return [
    'timezone' => 'Europe/Moscow',

    'default_beer_label' => env('APP_URL').'/img/product-placeholder.png',

    // Домен Untappd — untappd_beers.url исторически хранит относительный
    // путь (см. ResolveBeerStyleStage), UntappdBeer::url() приклеивает этот
    // домен на чтении, сама колонка теперь тоже пишется с ним.
    'untappd_base_url' => 'https://untappd.com',
];
