<?php

// Правила из локальной БД в порядке priority, id. При равном priority сохраняется порядок списка.
return [
    'rules' => [
        ['slug' => 'beer', 'type' => 'alcohol', 'match_when' => 'default', 'value' => null, 'priority' => 10, 'is_active' => true],
        ['slug' => 'mead', 'type' => 'alcohol', 'match_when' => 'style', 'value' => 'mead', 'priority' => 20, 'is_active' => true],
        ['slug' => 'cider', 'type' => 'alcohol', 'match_when' => 'style', 'value' => 'cider', 'priority' => 30, 'is_active' => true],
        ['slug' => 'non-alcoholic', 'type' => 'alcohol', 'match_when' => 'no_abv', 'value' => null, 'priority' => 40, 'is_active' => true],
        ['slug' => 'sauce', 'type' => 'alcohol', 'match_when' => 'style', 'value' => 'sauce', 'priority' => 50, 'is_active' => true],
        ['slug' => 'pet-tare-packages', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'пэт', 'priority' => 60, 'is_active' => true],
        ['slug' => 'pet-tare-packages', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'тара', 'priority' => 70, 'is_active' => true],
        ['slug' => 'pet-tare-packages', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'пакет', 'priority' => 80, 'is_active' => true],
        ['slug' => 'for-beer', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'арахис', 'priority' => 90, 'is_active' => true],
        ['slug' => 'for-beer', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'снэки', 'priority' => 100, 'is_active' => true],
        ['slug' => 'for-beer', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'чипсы', 'priority' => 110, 'is_active' => true],
        ['slug' => 'clothes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'футболка', 'priority' => 120, 'is_active' => true],
        ['slug' => 'clothes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'толстовка', 'priority' => 130, 'is_active' => true],
        ['slug' => 'clothes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'шапка', 'priority' => 140, 'is_active' => true],
        ['slug' => 'attributes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'шеврон', 'priority' => 150, 'is_active' => true],
        ['slug' => 'attributes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'маска', 'priority' => 160, 'is_active' => true],
        ['slug' => 'attributes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'флаг', 'priority' => 170, 'is_active' => true],
        ['slug' => 'attributes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'атрибутика', 'priority' => 180, 'is_active' => true],
        ['slug' => 'attributes', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'коврик', 'priority' => 190, 'is_active' => true],
        ['slug' => 'souvenirs', 'type' => 'alcohol', 'match_when' => 'advent', 'value' => null, 'priority' => 200, 'is_active' => true],
        ['slug' => 'souvenirs', 'type' => 'accessory_title', 'match_when' => null, 'value' => 'адвент', 'priority' => 210, 'is_active' => true],
        ['slug' => 'probes', 'type' => 'contains', 'match_when' => null, 'value' => 'пробники', 'priority' => 220, 'is_active' => true],
        ['slug' => 'souvenir-glasses', 'type' => 'contains', 'match_when' => null, 'value' => 'сувенирные бокалы', 'priority' => 230, 'is_active' => true],
    ],
];
