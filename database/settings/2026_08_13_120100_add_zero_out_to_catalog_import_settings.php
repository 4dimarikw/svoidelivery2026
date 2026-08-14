<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('catalog_import.zero_out_missing', true);
        $this->migrator->add('catalog_import.zero_out_max_percent', 20);
    }

    public function down(): void
    {
        foreach ([
            'catalog_import.zero_out_missing',
            'catalog_import.zero_out_max_percent',
        ] as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }
};
