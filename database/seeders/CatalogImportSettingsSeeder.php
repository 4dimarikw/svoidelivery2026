<?php

namespace Database\Seeders;

use Domain\Catalog\Enums\ProductStatus;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\CategoryRegistry;

/**
 * Дефолты 1:1 с database/settings/2026_08_03_100300_create_catalog_import_settings.php.
 * fallback_slug='not-defined' требует, чтобы такая категория уже существовала —
 * запускать после CategorySeeder (см. DatabaseSeeder).
 */
class CatalogImportSettingsSeeder extends AbstractSettingsSeeder
{
    protected function settingsClass(): string
    {
        return CatalogImportSettings::class;
    }

    protected function defaults(): array
    {
        return [
            'alcohol_marker' => 'Алкогольная продукция',
            'accessory_marker' => 'Сопутствующие товары',
            'advent_marker' => 'адвент',
            'fallback_slug' => 'not-defined',
            'zero_out_missing' => true,
            'zero_out_max_percent' => 20,
            'product_status' => ProductStatus::PUBLISHED->value,
            'new_days' => 7,
            'last_catalog_update' => null,
            'cache' => false,
            'extra_charge' => 0,
        ];
    }

    protected function nonResettable(): array
    {
        return ['last_catalog_update'];
    }

    protected function afterSeed(): void
    {
        // Маркеры и fallback_slug кэшируются реестром — тот же флаш,
        // что CatalogImportSettingsPage::store() делает после ручного сохранения.
        CategoryRegistry::flush();
    }
}
