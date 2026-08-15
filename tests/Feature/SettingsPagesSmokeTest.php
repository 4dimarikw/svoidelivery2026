<?php

namespace Tests\Feature;

use App\MoonShine\Pages\CatalogImportSettingsPage;
use App\MoonShine\Pages\SiteSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Infrastructure\Settings\SiteSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Tests\TestCase;

/**
 * Регресс на ликвидацию GeneralSettings: часть её свойств переехала в
 * CatalogImportSettings, часть — в SiteSettings (см. database/settings/
 * create_catalog_import_settings и create_site_settings). Spatie бросает
 * MissingSettings, если у объявленного свойства нет строки в репозитории —
 * этот тест ловит рассинхрон между классом настроек и его settings-миграциями.
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

        // Пишем значения напрямую в таблицу settings, а не через
        // app(Settings::class)->fill()->save() — тот вызов сам форсирует
        // Settings::loadValues() на том же scoped-инстансе, который потом
        // резолвит и код страницы, и маскирует баг ленивой загрузки
        // (см. ниже). Здесь же страница обязана получить нетронутый,
        // ещё не загруженный объект — как при реальном GET-запросе.
        SettingsProperty::query()->where('group', 'site')->where('name', 'site_name')
            ->update(['payload' => json_encode('SMOKE-SITE-NAME')]);
        SettingsProperty::query()->where('group', 'catalog_import')->where('name', 'alcohol_marker')
            ->update(['payload' => json_encode('SMOKE-MARKER')]);

        // Значения из БД должны реально попасть в форму — регресс на баг, когда
        // ->fill(get_object_vars($settings)) отдавал пустой массив из-за
        // ленивой загрузки свойств Spatie\LaravelSettings\Settings (unset() в
        // конструкторе, значения подгружаются только через __get()/toArray()).
        $this->actingAs($admin, 'moonshine')
            ->get(app(SiteSettingsPage::class)->getUrl())
            ->assertOk()
            ->assertSee('SMOKE-SITE-NAME', false);

        $this->actingAs($admin, 'moonshine')
            ->get(app(CatalogImportSettingsPage::class)->getUrl())
            ->assertOk()
            ->assertSee('SMOKE-MARKER', false);
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
