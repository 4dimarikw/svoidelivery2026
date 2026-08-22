<?php

declare(strict_types=1);

/**
 * Реестр соцсетей/мессенджеров, ссылки на которые пользователь может
 * указать в профиле (Domain\Profile\Models\Profile::$social_links, json).
 * Новая сеть добавляется строкой сюда — без миграции и без правки
 * <x-ui.*>/MoonShine-полей, которые строятся по этому списку.
 * label — название бренда, не переводится, поэтому не в lang/*.
 */
return [
    'networks' => [
        'telegram' => [
            'label' => 'Telegram',
            'placeholder' => 'https://t.me/...',
        ],
        'vk' => [
            'label' => 'VK',
            'placeholder' => 'https://vk.com/...',
        ],
        'max' => [
            'label' => 'MAX',
            'placeholder' => 'https://max.ru/...',
        ],
    ],
];
