<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Общие настройки сайта: название, автовход Telegram Mini App, тестовый пользователь, адрес уведомлений и лимит
 * обновления Untappd. Редактируются на странице SiteSettings.
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
