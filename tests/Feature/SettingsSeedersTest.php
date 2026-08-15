<?php

namespace Tests\Feature;

use Database\Seeders\CatalogImportSettingsSeeder;
use Database\Seeders\VkSyncSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Infrastructure\Settings\VKSyncSettings;
use ReflectionClass;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\LaravelSettings\Settings;
use Tests\TestCase;

/**
 * CatalogImportSettingsSeeder/VkSyncSettingsSeeder: чинят недостающие строки
 * настроек, не трогают существующие значения без force, сбрасывают их к
 * дефолту с force (кроме журнальных полей), и не расходятся со свойствами
 * классов настроек.
 */
class SettingsSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_adds_missing_property(): void
    {
        app(SettingsMigrator::class)->delete('vk_sync.cron');

        $this->seedFresh(VkSyncSettingsSeeder::class);

        $this->assertSame('1 * * * *', $this->fresh(VKSyncSettings::class)->cron);
    }

    public function test_run_without_force_does_not_overwrite_existing_value(): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->count = 42;
        $settings->save();

        $this->seedFresh(VkSyncSettingsSeeder::class);

        $this->assertSame(42, $this->fresh(VKSyncSettings::class)->count);
    }

    public function test_run_with_force_resets_value_to_default(): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->count = 42;
        $settings->save();

        $this->seedFresh(VkSyncSettingsSeeder::class, force: true);

        $this->assertSame(1, $this->fresh(VKSyncSettings::class)->count);
    }

    public function test_force_does_not_reset_non_resettable_journal_fields(): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->last_update = 123456;
        $settings->save();

        $this->seedFresh(VkSyncSettingsSeeder::class, force: true);

        $this->assertSame(123456, $this->fresh(VKSyncSettings::class)->last_update);
    }

    public function test_catalog_import_settings_seeder_defaults_cover_every_property(): void
    {
        $this->assertSeederCoversAllProperties(CatalogImportSettings::class, CatalogImportSettingsSeeder::class);
    }

    public function test_vk_sync_settings_seeder_defaults_cover_every_property(): void
    {
        $this->assertSeederCoversAllProperties(VKSyncSettings::class, VkSyncSettingsSeeder::class);
    }

    private function seedFresh(string $seederClass, bool $force = false): void
    {
        (new $seederClass)->run($force);
    }

    /** @param class-string<Settings> $settingsClass */
    private function fresh(string $settingsClass): Settings
    {
        app()->forgetInstance($settingsClass);

        return app($settingsClass);
    }

    /**
     * Публичные свойства класса настроек должны один в один совпадать с
     * ключами defaults() сидера — новое свойство без строки в сидере
     * останется без repair-защиты от MissingSettings.
     */
    private function assertSeederCoversAllProperties(string $settingsClass, string $seederClass): void
    {
        $properties = array_map(
            static fn ($property) => $property->getName(),
            (new ReflectionClass($settingsClass))->getProperties(\ReflectionProperty::IS_PUBLIC),
        );

        $seeder = new ReflectionClass($seederClass);
        $method = $seeder->getMethod('defaults');
        $method->setAccessible(true);
        $defaults = $method->invoke($seeder->newInstanceWithoutConstructor());

        sort($properties);
        $defaultKeys = array_keys($defaults);
        sort($defaultKeys);

        $this->assertSame($properties, $defaultKeys);
    }
}
