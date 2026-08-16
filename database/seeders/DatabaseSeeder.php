<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            CategorySeeder::class,
            ContainerSeeder::class,
            PropertySeeder::class,
            SeoSeeder::class,
            MoonshinePermissionSeeder::class,
            // После CategorySeeder — CatalogImportSettingsSeeder ссылается на
            // категорию 'not-defined' как fallback_slug.
            CatalogImportSettingsSeeder::class,
            VkSyncSettingsSeeder::class,
            SiteSettingsSeeder::class,
        ]);
    }
}
