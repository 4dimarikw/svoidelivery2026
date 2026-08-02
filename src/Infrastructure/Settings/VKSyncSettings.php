<?php

namespace Infrastructure\Settings;

use Spatie\LaravelSettings\Settings;

class VKSyncSettings extends Settings
{
    public int|string|null $last_update = null;

    public ?string $domain = '';

    public ?int $count = 1;

    public ?string $cron = '1 * * * *';

    public bool $active = false;

    public ?string $post_status = 'draft';

    public ?array $post_types = ['post'];

    public static function group(): string
    {
        return 'vk_sync';
    }
}
