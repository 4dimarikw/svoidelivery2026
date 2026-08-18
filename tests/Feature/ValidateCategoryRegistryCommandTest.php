<?php

namespace Tests\Feature;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Tests\TestCase;

/**
 * migration 2026_08_03_100200_seed_category_registry_from_config already
 * transplants a valid registry into every RefreshDatabase-migrated test DB,
 * so these tests add EXTRA rules on top of it (with unique slugs/keywords
 * that don't collide with the migrated ones) rather than recreating
 * 'beer'/'mead'/'not-defined'/etc. from scratch.
 */
class ValidateCategoryRegistryCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $slug, string $name): Category
    {
        return Category::query()->create([
            'slug' => $slug,
            'code' => str_replace('-', '_', $slug),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_passes_on_the_migrated_registry(): void
    {
        $this->artisan('catalog:validate-registry')->assertExitCode(0);
    }

    public function test_fails_on_duplicate_default_alcohol_rule(): void
    {
        // 'beer' already carries the sole active alcohol/default rule from
        // the seed migration — adding a second one anywhere else must fail.
        $extra = $this->makeCategory('test-extra-default', 'Тестовая доп. категория');

        CategoryMatchRule::query()->create([
            'category_id' => $extra->id,
            'type' => CategoryMatchType::Alcohol,
            'match_when' => CategoryMatchWhen::DefaultRule,
            'priority' => 999,
            'is_active' => true,
        ]);

        $this->artisan('catalog:validate-registry')->assertExitCode(1);
    }

    public function test_fails_on_duplicate_accessory_title_keyword(): void
    {
        $a = $this->makeCategory('test-accessory-a', 'Тест А');
        $b = $this->makeCategory('test-accessory-b', 'Тест Б');

        foreach ([$a, $b] as $category) {
            CategoryMatchRule::query()->create([
                'category_id' => $category->id,
                'type' => CategoryMatchType::AccessoryTitle,
                'value' => 'уникальный-тест-keyword',
                'priority' => 999,
                'is_active' => true,
            ]);
        }

        $this->artisan('catalog:validate-registry')->assertExitCode(1);
    }

    public function test_fails_when_fallback_slug_missing(): void
    {
        $settings = app(CatalogImportSettings::class);
        $settings->fallback_slug = 'this-slug-does-not-exist';
        $settings->save();

        $this->artisan('catalog:validate-registry')->assertExitCode(1);
    }

    public function test_passes_when_fallback_category_is_inactive(): void
    {
        // Отключение fallback-категории — штатный способ скрыть с витрины
        // нераспознанные товары (ProductBuilder::inCategories()), а не
        // ошибка реестра — команда должна пройти, только предупредить.
        $settings = app(CatalogImportSettings::class);
        $category = Category::query()->where('slug', $settings->fallback_slug)->firstOrFail();
        $category->is_active = false;
        $category->save();

        $this->artisan('catalog:validate-registry')
            ->expectsOutputToContain('неактивна')
            ->assertExitCode(0);
    }
}
