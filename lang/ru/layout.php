<?php

// App-owned header/footer chrome copy. Same ownership rule as account.php —
// not in auth.php/validation.php (laravel-lang) and not in ui.php
// (<x-ui.*> component-internal strings only).

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

    'footer' => [
        'age_notice' => '18+ · Алкоголь может быть вреден для вашего здоровья',
        'rights' => 'Все права защищены',
    ],
];
