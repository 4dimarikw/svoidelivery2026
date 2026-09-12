<?php

namespace Tests\Feature;

use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\CategoryRegistry;
use Tests\TestCase;

/**
 * CategoryRegistry кеширует категории (catalog:import прогоняет
 * резолвер на каждой строке CSV) — правки в админке должны попадать в
 * следующий же вызов, без ручного сброса кэша.
 */
class CategoryRegistryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_category_invalidates_the_cache(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-cache-category',
            'code' => 'test_cache_category',
            'name' => 'Тестовая',
            'is_active' => true,
        ]);

        $registry = new CategoryRegistry;
        $this->assertSame('Тестовая', $registry->property('test-cache-category', 'name'));

        $category->update(['name' => 'Тестовая обновлено']);

        $registry = new CategoryRegistry;
        $this->assertSame('Тестовая обновлено', $registry->property('test-cache-category', 'name'));
    }

    public function test_config_changes_are_not_hidden_by_shared_cache(): void
    {
        $first = new CategoryRegistry;
        $this->assertNotEmpty($first->rulesOfType('contains'));
        config(['category_matching.rules' => []]);
        $this->assertSame([], (new CategoryRegistry)->rulesOfType('contains'));
    }

    public function test_saving_settings_does_not_auto_invalidate_but_page_forgets_cache_manually(): void
    {
        // Резолюционные маркеры не кэшируются CategoryRegistry (читаются
        // напрямую из CatalogImportSettings при каждом __construct), только
        // $categories кешируются — эта проверка документирует,
        // что смена fallback_slug видна сразу без сброса CACHE_KEY.
        $settings = app(CatalogImportSettings::class);
        $settings->fallback_slug = 'custom-fallback';
        $settings->save();

        $registry = new CategoryRegistry;
        $this->assertSame('custom-fallback', $registry->fallback());
    }

    public function test_registry_is_bound_as_a_singleton_and_flush_resets_it(): void
    {
        // Pipeline получает CategoryRegistry для каждой строки CSV. Контейнер должен возвращать один экземпляр,
        // чтобы кеш не читался повторно.
        $first = app(CategoryRegistry::class);
        $second = app(CategoryRegistry::class);
        $this->assertSame($first, $second);

        CategoryRegistry::flush();

        $third = app(CategoryRegistry::class);
        $this->assertNotSame($first, $third);
    }

    public function test_registry_never_queries_the_legacy_rules_table(): void
    {
        CategoryRegistry::flush();
        DB::enableQueryLog();
        new CategoryRegistry;
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('category_match_rules', $query['query']);
        }
        DB::disableQueryLog();
    }
}
