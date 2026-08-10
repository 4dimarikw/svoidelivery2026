<?php

return [
    'settings' => [
        'site_name' => 'SvoiDelivery',
        'telegram_autologin' => false,
    ],
    'sections' => [
        ['key' => 'home', 'title' => 'Каталог', 'route_name' => 'home', 'fragment' => '', 'sort_order' => 10],
        ['key' => 'about', 'title' => 'О нас', 'route_name' => 'about', 'fragment' => '', 'sort_order' => 20],
    ],
    'menus' => [
        'main' => [
            'title' => 'Главное меню',
            'is_active' => true,
            'items' => [
                ['key' => 'home', 'parent_key' => null, 'section_key' => 'home', 'external_url' => null, 'label' => 'Каталог', 'open_in_new_tab' => false, 'is_active' => false],
                ['key' => 'about', 'parent_key' => null, 'section_key' => 'about', 'external_url' => null, 'label' => null, 'open_in_new_tab' => false, 'is_active' => true],
            ],
        ],
    ],
    'blocks' => [],
];
