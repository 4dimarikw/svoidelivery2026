<?php

use Domain\Catalog\Enums\ProductStatus;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Настройки импорта каталога 1С: маркеры сегментов "Категория" для
 * CategorySlugResolver (см. CategoryRegistry), предохранитель обнуления
 * остатков (см. ZeroOutStaleProductsAction), а также настройки, изначально
 * жившие в GeneralSettings (product_status, new_days, cache,
 * last_catalog_update, extra_charge) — они логически про каталог/импорт, а
 * не про «общие» настройки сайта. См. Infrastructure\Settings\CatalogImportSettings.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('catalog_import.alcohol_marker', 'Алкогольная продукция');
        $this->migrator->add('catalog_import.accessory_marker', 'Сопутствующие товары');
        $this->migrator->add('catalog_import.advent_marker', 'адвент');
        $this->migrator->add('catalog_import.fallback_slug', 'not-defined');
        $this->migrator->add('catalog_import.zero_out_missing', true);
        $this->migrator->add('catalog_import.zero_out_max_percent', 20);
        $this->migrator->add('catalog_import.product_status', ProductStatus::DRAFT->value);
        $this->migrator->add('catalog_import.new_days', 7);
        $this->migrator->add('catalog_import.last_catalog_update', null);
        $this->migrator->add('catalog_import.cache', false);
        $this->migrator->add('catalog_import.extra_charge', 0);
    }

    public function down(): void
    {
        foreach ([
            'catalog_import.alcohol_marker',
            'catalog_import.accessory_marker',
            'catalog_import.advent_marker',
            'catalog_import.fallback_slug',
            'catalog_import.zero_out_missing',
            'catalog_import.zero_out_max_percent',
            'catalog_import.product_status',
            'catalog_import.new_days',
            'catalog_import.last_catalog_update',
            'catalog_import.cache',
            'catalog_import.extra_charge',
        ] as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }
};
