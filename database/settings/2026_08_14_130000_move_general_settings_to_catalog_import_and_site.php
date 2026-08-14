<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Ликвидирует остаток GeneralSettings: extra_charge (не относится к
 * каталогу больше остальных, но и не «общий» — оставлен рядом с остальными
 * каталожными настройками) переезжает в catalog_import, остальные три
 * (test_user_email/notify_email/untappd_update_limit) — в site, так как
 * это единственный класс настроек с полноценной админ-страницей общего
 * назначения. После этой миграции группа `general` пуста, а сам класс
 * GeneralSettings удалён из кода.
 */
return new class extends SettingsMigration
{
    private const MAP = [
        'general.extra_charge' => 'catalog_import.extra_charge',
        'general.test_user_email' => 'site.test_user_email',
        'general.notify_email' => 'site.notify_email',
        'general.untappd_update_limit' => 'site.untappd_update_limit',
    ];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            $this->migrator->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $from => $to) {
            $this->migrator->rename($to, $from);
        }
    }
};
