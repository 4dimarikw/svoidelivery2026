<?php

// Copy for bot replies (App\Telegraph\WebhookHandler) — a different audience
// than account.php (site page copy) or ui.php (<x-ui.*> component-internal
// strings). Deliberately its own file.

return [
    'link_code_invalid' => 'This link has expired or was already used. Open your profile page on the site and try again.',
    'linked' => 'Done! Your site account is now linked to this chat.',
    'linked_admin' => 'Done! Your admin account is now linked to this chat.',
];
