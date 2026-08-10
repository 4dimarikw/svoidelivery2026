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

    public static function group(): string
    {
        return 'site';
    }
}
