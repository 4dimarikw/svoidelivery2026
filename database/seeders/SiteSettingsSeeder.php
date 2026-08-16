<?php

namespace Database\Seeders;

use Infrastructure\Settings\SiteSettings;

/**
 * Дефолты 1:1 с database/settings/2026_08_06_120003_create_site_settings.php.
 */
class SiteSettingsSeeder extends AbstractSettingsSeeder
{
    protected function settingsClass(): string
    {
        return SiteSettings::class;
    }

    protected function defaults(): array
    {
        return [
            'site_name' => '',
            'telegram_autologin' => false,
            'test_user_email' => null,
            'notify_email' => null,
            'untappd_update_limit' => 0,
        ];
    }
}
