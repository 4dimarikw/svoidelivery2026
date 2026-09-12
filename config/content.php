<?php

return [
    // Ключ типа => Blade-компонент (config('content.views.'.$block->type)),
    // единственная точка выбора view для публичного рендера (см. §10
    // docs/cms-ai-agent-guideline.md) — неизвестный/незарегистрированный тип
    // остаётся без view и не выводится (ContentCms\Models\ContentBlock::scopeRenderable()).
    // footer_info сюда не входит: подвал не идёт через диспетчер
    // <x-content.sections> (тот — для произвольного списка блоков секции
    // страницы), Domain\Content\Actions\Content\LoadSiteFooter отдаёт
    // единственный блок напрямую, footer.blade.php читает его content сам.
    'views' => [
        'about_info' => 'components.content.about-info',
        'terms_text' => 'components.content.terms-text',
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
