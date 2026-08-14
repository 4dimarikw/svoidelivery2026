<?php

namespace Infrastructure\Settings;

use Spatie\LaravelSettings\Settings;

class SiteSettings extends Settings
{
    public string $site_name;

    // Автовход через Telegram Mini App (см. <x-ui.telegram-autologin>,
    // TelegramLoginController::webapp()) — по умолчанию выключен, включается
    // в MoonShine на "Настройки сайта" (SiteSettingsPage).
    public bool $telegram_autologin;

    /** Email тестового пользователя для кнопки "Тестовый заказ" (OrderIndexPage::createTestOrder()). Перенесено из GeneralSettings. */
    public ?string $test_user_email = null;

    /** Email для уведомлений. Не используется ни одним читателем кода — перенесено из GeneralSettings как есть. */
    public ?string $notify_email = null;

    /** Лимит обновлений Untappd за прогон. Не используется ни одним читателем кода — перенесено из GeneralSettings как есть. */
    public int $untappd_update_limit = 0;

    public static function group(): string
    {
        return 'site';
    }
}
