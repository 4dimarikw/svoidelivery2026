<?php

return [
    'default_delivery_type' => 'Служба доставки',
    'default_payment_method' => 'При получении',

    'ftp_upload' => [
        'disk' => 'ftp',
        'dir' => env('DB_1C_FTP_ORDERS_DIR', 'Orders'),
        // Отключение останавливает сеть, но сохраняет новые заявки для возобновления.
        'outbox_enabled' => (bool) env('ORDER_FTP_OUTBOX_ENABLED', true),
    ],
];
