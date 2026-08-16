<?php

// App-owned header/mobile-nav chrome copy. Same ownership rule as account.php —
// not in auth.php/validation.php (laravel-lang) and not in ui.php
// (<x-ui.*> component-internal strings only). Footer text is CMS-driven now
// (Domain\Content, type=footer_info, see resources/views/components/layouts/footer.blade.php).

return [
    'nav' => [
        'register' => 'Регистрация',
        'logout' => 'Выйти',
        'account' => 'Личный кабинет',
        'favorites' => 'Избранное',
        'cart' => 'Корзина',
        // Добавлены для <x-ui.mobile-nav> — нижнего меню на мобиле.
        'catalog' => 'Каталог',
        'profile' => 'Профиль',
        'about' => 'О нас',
        'primary' => 'Основная навигация',
    ],
];
