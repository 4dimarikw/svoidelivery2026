<?php

return [
    // Только точные имена; новые CDN добавляются после проверки источника.
    'untappd_hosts' => ['api.bcraftfest.ru', 'untappd.com'],
    'max_bytes' => 10 * 1024 * 1024,
    'connect_timeout' => 5,
    'timeout' => 15,
    'max_dimension' => 8192,
    'max_pixels' => 20_000_000,
];
