<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Переносит настройки, относящиеся к каталогу/импорту, из группы `general`
 * в группу `catalog_import` — они читались/писались через GeneralSettings,
 * хотя логически принадлежат CatalogImportSettings (единственной из двух,
 * у которой вообще есть страница в админке).
 */
return new class extends SettingsMigration
{
    private const PROPERTIES = [
        'product_flags',
        'product_status',
        'last_catalog_update',
        'new_days',
        'cache',
    ];

    public function up(): void
    {
        foreach (self::PROPERTIES as $property) {
            $this->migrator->rename("general.{$property}", "catalog_import.{$property}");
        }
    }

    public function down(): void
    {
        foreach (self::PROPERTIES as $property) {
            $this->migrator->rename("catalog_import.{$property}", "general.{$property}");
        }
    }
};
