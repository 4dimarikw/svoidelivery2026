<?php

// App-owned header/footer chrome copy. Same ownership rule as account.php —
// not in auth.php/validation.php (laravel-lang) and not in ui.php
// (<x-ui.*> component-internal strings only).

return [
    'nav' => [
        'register' => 'Sign up',
        'logout' => 'Log out',
        'account' => 'My account',
        'favorites' => 'Favorites',
        'cart' => 'Cart',
    ],

    'footer' => [
        'age_notice' => '18+ · Alcohol may be harmful to your health',
        'rights' => 'All rights reserved',
    ],
];
