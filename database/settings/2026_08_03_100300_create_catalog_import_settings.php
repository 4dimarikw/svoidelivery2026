<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('catalog_import.alcohol_marker', 'Алкогольная продукция');
        $this->migrator->add('catalog_import.accessory_marker', 'Сопутствующие товары');
        $this->migrator->add('catalog_import.advent_marker', 'адвент');
        $this->migrator->add('catalog_import.fallback_slug', 'not-defined');
    }
};
