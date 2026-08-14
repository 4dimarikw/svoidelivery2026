<?php

namespace Tests\Feature;

use App\MoonShine\Pages\CatalogImportSettingsPage;
use App\MoonShine\Pages\SiteSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Infrastructure\Settings\SiteSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Регресс на ликвидацию GeneralSettings: часть её свойств переехала в
 * CatalogImportSettings, часть — в SiteSettings (см. settings-миграции
 * 2026_08_14_120000 / 2026_08_14_130000). Spatie бросает MissingSettings,
 * если у объявленного свойства нет строки в репозитории — этот тест ловит
 * рассинхрон между классом настроек и его settings-миграциями/fixture.
 */
class SettingsPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_and_catalog_import_settings_pages_render(): void
    {
        $admin = MoonshineUser::query()->create([
            'email' => 'smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);

        $this->actingAs($admin, 'moonshine')
            ->get(app(SiteSettingsPage::class)->getUrl())
            ->assertOk();

        $this->actingAs($admin, 'moonshine')
            ->get(app(CatalogImportSettingsPage::class)->getUrl())
            ->assertOk();
    }

    public function test_site_and_catalog_import_settings_resolve_without_missing_properties(): void
    {
        $site = app(SiteSettings::class);
        $catalogImport = app(CatalogImportSettings::class);

        $this->assertArrayHasKey('test_user_email', $site->toArray());
        $this->assertArrayHasKey('notify_email', $site->toArray());
        $this->assertArrayHasKey('untappd_update_limit', $site->toArray());

        $this->assertArrayHasKey('extra_charge', $catalogImport->toArray());
    }
}
