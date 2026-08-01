<?php

// App-owned UI strings for the <x-ui.*> component library. Keep these keys
// out of auth.php / validation.php — those are managed by laravel-lang/common
// and get overwritten by `composer post-update-cmd` → `artisan lang:update`.

return [
    'skip_to_content' => 'Skip to content',

    'password' => [
        'show' => 'Show password',
        'hide' => 'Hide password',
    ],

    'divider' => [
        'or' => 'or',
    ],

    'copy' => [
        'action' => 'Copy',
        'copied' => 'Copied',
    ],

    'resend' => [
        'action' => 'Resend code',
        'wait' => 'Resend in :seconds s',
    ],

    'required' => 'required field',
    'optional' => 'optional',

    'alert' => [
        'close' => 'Close',
    ],
];
