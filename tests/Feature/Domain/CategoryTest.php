<?php

namespace Tests\Feature\Domain;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Services\CatalogImport\CategoryRegistry;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_name_only_when_blank(): void
    {
        $category = Category::query()->create([
            'code' => 'test_slug_gen',
            'name' => 'Крафтовое пиво',
            'is_active' => true,
        ]);

        $this->assertSame('kraftovoe-pivo', $category->slug);
    }

    public function test_explicit_slug_is_never_overwritten_by_name_changes(): void
    {
        $category = Category::query()->create([
            'code' => 'test_slug_fixed',
            'name' => 'Исходное имя',
            'slug' => 'test-fixed-import-key', // импорт-ключ, намеренно не совпадает с name
            'is_active' => true,
        ]);

        $category->update(['name' => 'Совсем другое имя']);

        $this->assertSame('test-fixed-import-key', $category->fresh()->slug);
    }

    public function test_saving_a_category_flushes_the_registry_cache(): void
    {
        // Валидная по форме, но заведомо устаревшая (пустая) полезная нагрузка —
        // CategoryRegistry::__construct() не проверяет форму, просто доверяет кэшу.
        Cache::put(CategoryRegistry::CACHE_KEY, ['categories' => [], 'rules' => []], now()->addHour());
        $stale = app(CategoryRegistry::class);

        Category::query()->create([
            'code' => 'test_flush_on_save',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        $this->assertNull(Cache::get(CategoryRegistry::CACHE_KEY));
        // flush() тоже забывает резолвленный singleton — следующий app() вызов
        // не должен возвращать инстанс, построенный ДО этого сохранения.
        $this->assertNotSame($stale, app(CategoryRegistry::class));
    }

    public function test_deleting_a_category_flushes_the_registry_cache(): void
    {
        $category = Category::query()->create([
            'code' => 'test_flush_on_delete',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        Cache::put(CategoryRegistry::CACHE_KEY, ['stale' => true], now()->addHour());

        $category->delete();

        $this->assertNull(Cache::get(CategoryRegistry::CACHE_KEY));
    }

    public function test_match_rules_relation_is_ordered_by_priority_then_id(): void
    {
        $category = Category::query()->create([
            'code' => 'test_order_relation',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        $second = CategoryMatchRule::query()->create([
            'category_id' => $category->id,
            'type' => CategoryMatchType::Contains,
            'value' => 'нидл-2',
            'priority' => 20,
            'is_active' => true,
        ]);
        $first = CategoryMatchRule::query()->create([
            'category_id' => $category->id,
            'type' => CategoryMatchType::Contains,
            'value' => 'нидл-1',
            'priority' => 10,
            'is_active' => true,
        ]);

        $ids = $category->matchRules()->pluck('id')->all();

        $this->assertSame([$first->id, $second->id], $ids);
    }
}
