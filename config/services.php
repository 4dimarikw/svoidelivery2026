<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bcraftfest' => [
        'base_url' => env('BCRAFTFEST_API_URL', 'https://api.bcraftfest.ru/api/v1'),
        'db' => env('BCRAFTFEST_DB', 1),
    ],

    'catalog_1c_ftp' => [
        'host' => env('DB_1C_FTP_SERVER'),
        'port' => (int) env('DB_1C_FTP_PORT', 21),
        'username' => env('DB_1C_FTP_LOGIN'),
        'password' => env('DB_1C_FTP_PASSWORD'),
    ],

];
