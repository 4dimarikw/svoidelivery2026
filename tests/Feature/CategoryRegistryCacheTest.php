<?php

namespace Tests\Feature;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\CategoryRegistry;
use Tests\TestCase;

/**
 * CategoryRegistry кэширует реестр целиком (catalog:import прогоняет
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

    public function test_saving_a_match_rule_invalidates_the_cache(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-cache-category',
            'code' => 'test_cache_category',
            'name' => 'Тестовая',
            'is_active' => true,
        ]);

        $rule = CategoryMatchRule::query()->create([
            'category_id' => $category->id,
            'type' => CategoryMatchType::Contains,
            'match_when' => null,
            'value' => 'уникальный-нидл-один',
            'priority' => 10,
            'is_active' => true,
        ]);

        $findMyRule = fn (CategoryRegistry $registry): ?array => collect($registry->rulesOfType('contains'))
            ->first(fn (array $entry): bool => $entry['slug'] === 'test-cache-category');

        $registry = new CategoryRegistry;
        $this->assertSame('уникальный-нидл-один', $findMyRule($registry)['rule']['needle']);

        $rule->update(['value' => 'уникальный-нидл-два']);

        $registry = new CategoryRegistry;
        $this->assertSame('уникальный-нидл-два', $findMyRule($registry)['rule']['needle']);
    }

    public function test_saving_settings_does_not_auto_invalidate_but_page_forgets_cache_manually(): void
    {
        // Резолюционные маркеры не кэшируются CategoryRegistry (читаются
        // напрямую из CatalogImportSettings при каждом __construct), только
        // $categories/$orderedRules кэшируются — эта проверка документирует,
        // что смена fallback_slug видна сразу без сброса CACHE_KEY.
        $settings = app(CatalogImportSettings::class);
        $settings->fallback_slug = 'custom-fallback';
        $settings->save();

        $registry = new CategoryRegistry;
        $this->assertSame('custom-fallback', $registry->fallback());
    }

    public function test_registry_is_bound_as_a_singleton_and_flush_resets_it(): void
    {
        // catalog:import re-resolves CategoryRegistry via the pipeline
        // container per CSV row (Illuminate\Pipeline\Pipeline::carry()) —
        // it must stay the same instance across those resolutions, or the
        // Cache::rememberForever() guard in the constructor is pointless.
        $first = app(CategoryRegistry::class);
        $second = app(CategoryRegistry::class);
        $this->assertSame($first, $second);

        CategoryRegistry::flush();

        $third = app(CategoryRegistry::class);
        $this->assertNotSame($first, $third);
    }
}
