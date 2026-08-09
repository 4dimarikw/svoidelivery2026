<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.site_name', '');
    }

    public function down(): void
    {
        foreach ([
            'site.site_name',
        ] as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }
};
