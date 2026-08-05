<?php

namespace Tests\Unit\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\CategoryRegistry;
use Tests\TestCase;

/**
 * migration 2026_08_03_100200_seed_category_registry_from_config уже сеет
 * валидный реестр ('beer' и т.д.) — эти тесты добавляют категории/правила
 * поверх него с уникальными slug, как ValidateCategoryRegistryCommandTest.
 */
class CategoryRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'slug' => 'test-registry-cat',
            'code' => 'test_registry_cat',
            'name' => 'Тестовая',
            'is_active' => true,
            'expects_container' => true,
            'expects_volume' => true,
            'price_exempt' => true,
            'name_from_article' => true,
            'default_brand' => 'ДефолтБренд',
            'container_code' => 'pet',
        ], $overrides));
    }

    public function test_flag_accessors_read_the_registered_category(): void
    {
        $this->makeCategory();

        $registry = new CategoryRegistry;

        $this->assertTrue($registry->expectsContainer('test-registry-cat'));
        $this->assertTrue($registry->expectsVolume('test-registry-cat'));
        $this->assertTrue($registry->isPriceExempt('test-registry-cat'));
        $this->assertTrue($registry->nameFromArticle('test-registry-cat'));
        $this->assertSame('ДефолтБренд', $registry->defaultBrand('test-registry-cat'));
        $this->assertSame('pet', $registry->containerCode('test-registry-cat'));
    }

    public function test_flag_accessors_default_to_false_or_null_for_unknown_slug(): void
    {
        $registry = new CategoryRegistry;

        $this->assertFalse($registry->expectsContainer('no-such-slug'));
        $this->assertFalse($registry->expectsVolume('no-such-slug'));
        $this->assertFalse($registry->isPriceExempt('no-such-slug'));
        $this->assertFalse($registry->nameFromArticle('no-such-slug'));
        $this->assertNull($registry->defaultBrand('no-such-slug'));
        $this->assertNull($registry->containerCode('no-such-slug'));
    }

    public function test_rules_of_type_are_ordered_by_priority_then_id_not_by_category(): void
    {
        // Категория B создаётся раньше A, но её правило имеет больший priority —
        // порядок в rulesOfType() должен идти по priority, а не по id категории.
        $b = $this->makeCategory(['slug' => 'test-order-b', 'code' => 'test_order_b']);
        $a = $this->makeCategory(['slug' => 'test-order-a', 'code' => 'test_order_a']);

        CategoryMatchRule::query()->create([
            'category_id' => $b->id,
            'type' => CategoryMatchType::Contains,
            'value' => 'нидл-б',
            'priority' => 20,
            'is_active' => true,
        ]);

        CategoryMatchRule::query()->create([
            'category_id' => $a->id,
            'type' => CategoryMatchType::Contains,
            'value' => 'нидл-а',
            'priority' => 10,
            'is_active' => true,
        ]);

        $registry = new CategoryRegistry;
        $slugs = collect($registry->rulesOfType('contains'))
            ->filter(fn (array $entry) => in_array($entry['slug'], ['test-order-a', 'test-order-b'], true))
            ->pluck('slug')
            ->values()
            ->all();

        $this->assertSame(['test-order-a', 'test-order-b'], $slugs);
    }

    public function test_rule_with_blank_value_is_skipped(): void
    {
        // Регрессия: пустое value на accessory_title раньше собиралось в
        // keywords => [null] → пустая ветка альтернации → матчит всё подряд.
        $category = $this->makeCategory(['slug' => 'test-blank-value', 'code' => 'test_blank_value']);

        CategoryMatchRule::query()->create([
            'category_id' => $category->id,
            'type' => CategoryMatchType::AccessoryTitle,
            'value' => null,
            'priority' => 10,
            'is_active' => true,
        ]);

        $registry = new CategoryRegistry;
        $entries = collect($registry->rulesOfType('accessory_title'))
            ->filter(fn (array $entry) => $entry['slug'] === 'test-blank-value');

        $this->assertTrue($entries->isEmpty());
    }

    public function test_markers_are_read_from_seeded_settings(): void
    {
        $registry = new CategoryRegistry;

        $this->assertNotSame('', $registry->alcoholMarker());
        $this->assertNotSame('', $registry->accessoryMarker());
        $this->assertNotSame('', $registry->fallback());
    }
}
