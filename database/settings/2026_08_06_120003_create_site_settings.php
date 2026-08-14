<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Общие настройки сайта: site_name, автовход через Telegram Mini App
 * (telegram_autologin), а также настройки, изначально жившие в
 * GeneralSettings (test_user_email, notify_email, untappd_update_limit) —
 * SiteSettings единственный класс настроек с полноценной общей страницей
 * в админке. См. Infrastructure\Settings\SiteSettings.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.site_name', '');
        $this->migrator->add('site.telegram_autologin', false);
        $this->migrator->add('site.test_user_email', null);
        $this->migrator->add('site.notify_email', null);
        $this->migrator->add('site.untappd_update_limit', 0);
    }

    public function down(): void
    {
        foreach ([
            'site.site_name',
            'site.telegram_autologin',
            'site.test_user_email',
            'site.notify_email',
            'site.untappd_update_limit',
        ] as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }
};
