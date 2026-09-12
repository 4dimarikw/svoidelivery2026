<?php

namespace Tests\Feature;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\CatalogImportSettings;
use Tests\Support\ConfiguresCategoryMatching;
use Tests\TestCase;

/**
 * Исходные миграции создают корректный реестр категорий. Тесты добавляют к нему правила с уникальными slug и
 * ключевыми словами, проверяя нарушения ограничений.
 */
class ValidateCategoryRegistryCommandTest extends TestCase
{
    use ConfiguresCategoryMatching;
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
        // Категория beer содержит активное правило alcohol/default; второе такое правило должно вызвать ошибку
        // проверки.
        $extra = $this->makeCategory('test-extra-default', 'Тестовая доп. категория');

        $this->addMatchingRule([
            'slug' => $extra->slug,
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
            $this->addMatchingRule([
                'slug' => $category->slug,
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

    public function test_missing_rule_category_is_rejected_even_when_disabled(): void
    {
        $this->addMatchingRule(['slug' => 'missing-category', 'type' => 'contains',
            'value' => 'missing', 'is_active' => false]);
        $this->artisan('catalog:validate-registry')->assertExitCode(1);
    }

    public function test_invalid_rule_structure_is_reported(): void
    {
        config(['category_matching.rules' => [['type' => 'contains']]]);
        $this->artisan('catalog:validate-registry')->assertExitCode(1);
    }
}
