<?php

// App-owned header/mobile-nav chrome copy. Same ownership rule as account.php —
// not in auth.php/validation.php (laravel-lang) and not in ui.php
// (<x-ui.*> component-internal strings only). Footer text is CMS-driven now
// (Domain\Content, type=footer_info, see resources/views/components/layouts/footer.blade.php).

return [
    'nav' => [
        'register' => 'Sign up',
        'logout' => 'Log out',
        'account' => 'My account',
        'favorites' => 'Favorites',
        'cart' => 'Cart',
        // Added for <x-ui.mobile-nav> — mobile bottom navigation.
        'catalog' => 'Catalog',
        'profile' => 'Profile',
        'about' => 'About',
        'filters' => 'Filters',
        'primary' => 'Primary navigation',
    ],
];
