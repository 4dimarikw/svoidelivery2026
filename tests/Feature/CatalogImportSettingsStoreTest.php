<?php

namespace Tests\Feature;

use App\MoonShine\Pages\CatalogImportSettingsPage;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Регресс на TypeError при сохранении настроек импорта каталога:
 * request()->validate() отдаёт строки даже для правила 'integer' (оно только
 * проверяет формат, не кастует), а int-свойства CatalogImportSettings
 * присваивание строки не прощают (см. CatalogImportSettingsPage::store()).
 */
class CatalogImportSettingsStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_casts_integer_fields_before_assigning_to_settings(): void
    {
        $admin = MoonshineUser::query()->create([
            'email' => 'smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);

        // Категория 'not-defined' уже существует — её сеет data-миграция
        // 2026_08_03_100200_seed_category_registry_from_config.php.
        Category::query()->firstOrCreate(['slug' => 'not-defined'], Category::factory()->raw());

        $page = app(CatalogImportSettingsPage::class);

        // Значения — строки, как их реально шлёт HTML-форма (никакого
        // приведения типов на стороне клиента нет).
        $this->actingAs($admin, 'moonshine')
            ->post(route('moonshine.method', ['pageUri' => $page->getUriKey()]), [
                'method' => 'store',
                'alcohol_marker' => 'Алкоголь',
                'accessory_marker' => 'Сопутствующие',
                'advent_marker' => 'адвент',
                'fallback_slug' => 'not-defined',
                'zero_out_max_percent' => '35',
                'product_status' => ProductStatus::DRAFT->value,
                'new_days' => '14',
                'extra_charge' => '7',
                'zero_out_missing' => '1',
                'cache' => '1',
            ])
            ->assertOk();

        app()->forgetInstance(CatalogImportSettings::class);
        $settings = app(CatalogImportSettings::class);

        $this->assertSame(35, $settings->zero_out_max_percent);
        $this->assertSame(14, $settings->new_days);
        $this->assertSame(7, $settings->extra_charge);
    }
}
