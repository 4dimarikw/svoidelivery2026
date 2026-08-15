<?php

namespace Database\Seeders;

use Domain\Vk\Enums\VkPostStatus;
use Infrastructure\Settings\VKSyncSettings;

/**
 * Дефолты 1:1 с database/settings/2026_02_22_095159_create_vksync_settings.php.
 */
class VkSyncSettingsSeeder extends AbstractSettingsSeeder
{
    protected function settingsClass(): string
    {
        return VKSyncSettings::class;
    }

    protected function defaults(): array
    {
        return [
            'last_update' => null,
            'domain' => null,
            'count' => 1,
            'cron' => '1 * * * *',
            'active' => false,
            'post_status' => VkPostStatus::DRAFT->value,
            'post_types' => ['post'],
        ];
    }

    protected function nonResettable(): array
    {
        return ['last_update'];
    }
}
