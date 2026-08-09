<?php

use Domain\Content\Types\AboutBlockType;
use Domain\Content\Types\CharityBlockType;
use Domain\Content\Types\FaqBlockType;
use Domain\Content\Types\HeroBlockType;
use Domain\Content\Types\LocationBlockType;
use Domain\Content\Types\MarketBlockType;
use Domain\Content\Types\ProgramBlockType;

return [
    'views' => [
        'hero' => 'components.blocks.hero',
        'about' => 'components.blocks.about',
        'program' => 'components.blocks.program',
        'market' => 'components.blocks.market',
        'charity' => 'components.blocks.charity',
        'location' => 'components.blocks.location',
        'faq' => 'components.blocks.faq',
    ],
    'types' => [
        HeroBlockType::class,
        AboutBlockType::class,
        ProgramBlockType::class,
        MarketBlockType::class,
        CharityBlockType::class,
        LocationBlockType::class,
        FaqBlockType::class,
    ],
    'icons' => [
        'heart',
        'sparkles',
        'calendar-days',
        'map-pin',
        'gift',
        'shopping-bag',
        'musical-note',
        'users',
        'bus',
        'car',
        'information-circle',
        'check-circle',
        'store',
        'bike',
        'waves',
        'music',
        'palette',
        'utensils',
        'smile',
        'heart-handshake',
        'coffee',
        'shirt',
        'gem',
        'book-open',
        'apple',
        'clock',
        'map',
        'paw-print',
        'trees',
        'sun',
        'umbrella',
        'train',
        'taxi',
        'parking-circle',
    ],
];
