<?php

// App-owned UI strings for the <x-ui.*> component library. Keep these keys
// out of auth.php / validation.php — those are managed by laravel-lang/common
// and get overwritten by `composer post-update-cmd` → `artisan lang:update`.

return [
    'skip_to_content' => 'Перейти к содержимому',

    'password' => [
        'show' => 'Показать пароль',
        'hide' => 'Скрыть пароль',
    ],

    'divider' => [
        'or' => 'или',
    ],

    'copy' => [
        'action' => 'Копировать',
        'copied' => 'Скопировано',
    ],

    'resend' => [
        'action' => 'Отправить код ещё раз',
        'wait' => 'Повторно через :seconds с',
    ],

    'required' => 'обязательное поле',
    'optional' => 'необязательно',

    'alert' => [
        'close' => 'Закрыть',
    ],
];
