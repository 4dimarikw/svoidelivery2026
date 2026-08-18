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

    /**
     * Email(ы) для уведомления о новом заказе (App\Listeners\Order\HandleOrderCreated).
     * Список адресов через ";" — см. notifyEmails().
     */
    public ?string $notify_email = null;

    /** Лимит обновлений Untappd за прогон. Не используется ни одним читателем кода — перенесено из GeneralSettings как есть. */
    public int $untappd_update_limit = 0;

    public static function group(): string
    {
        return 'site';
    }

    /**
     * Адреса уведомления о новом заказе, распарсенные из notify_email
     * (список через ";"). Валидность каждого адреса гарантирует
     * SiteSettingsPage::save() (Infrastructure\Rules\EmailListRule),
     * здесь только разбор: split + trim + отбрасывание пустых частей.
     *
     * @return list<string>
     */
    public function notifyEmails(): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(';', (string) $this->notify_email),
        ), fn (string $email): bool => $email !== ''));
    }
}
