<?php

namespace Tests\Unit;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\CategorySlugResolver;
use Services\CatalogImport\Dto\RawRow;
use Tests\Support\ConfiguresCategoryMatching;
use Tests\TestCase;

/**
 * Проверяет четыре ветви CategorySlugResolver: alcohol, contains, accessory_title и резервную категорию.
 * Используется стартовый реестр, создаваемый исходными миграциями через RefreshDatabase.
 */
class CategorySlugResolverTest extends TestCase
{
    use ConfiguresCategoryMatching;
    use RefreshDatabase;

    private function row(array $data): RawRow
    {
        $columns = config('catalog_import.columns');

        $mapped = [];
        foreach ($data as $key => $value) {
            $mapped[$columns[$key]] = $value;
        }

        return new RawRow($mapped, 1);
    }

    public function test_alcohol_default_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Алкогольная продукция',
            'abv' => '5',
            'beer_style' => 'Lager',
            'name_full' => 'Пиво светлое',
        ]));

        $this->assertSame('beer', $slug);
    }

    public function test_alcohol_style_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Алкогольная продукция',
            'abv' => '4',
            'beer_style' => 'Traditional Mead',
            'name_full' => 'Мёд ставленый',
        ]));

        $this->assertSame('mead', $slug);
    }

    public function test_alcohol_no_abv_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Алкогольная продукция',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Квас',
        ]));

        $this->assertSame('non-alcoholic', $slug);
    }

    public function test_alcohol_advent_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Алкогольная продукция > Адвент календарь',
            'abv' => '5',
            'beer_style' => '',
            'name_full' => 'Адвент-календарь пивной',
        ]));

        $this->assertSame('souvenirs', $slug);
    }

    public function test_contains_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары>Оборудование>Пробники',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Набор пробников',
        ]));

        $this->assertSame('probes', $slug);
    }

    public function test_accessory_title_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Тара пэт 1л',
        ]));

        $this->assertSame('pet-tare-packages', $slug);
    }

    public function test_fallback_branch(): void
    {
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Что-то совсем другое',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Неизвестный товар',
        ]));

        $this->assertSame('not-defined', $slug);
    }

    /**
     * Ключевое слово с / должно корректно экранироваться для регулярного выражения в
     * resolveAccessoryTitleSlug().
     */
    public function test_accessory_keyword_containing_slash_does_not_break_the_pattern(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-slash-keyword',
            'code' => 'test_slash_keyword',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        $this->addMatchingRule([
            'slug' => $category->slug,
            'type' => CategoryMatchType::AccessoryTitle,
            'value' => 'тара 1/2',
            'priority' => 5,
            'is_active' => true,
        ]);

        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Тара 1/2 литра',
        ]));

        $this->assertSame('test-slash-keyword', $slug);

        // И существующие ключевые слова (без "/") продолжают резолвиться
        // штатно — сломанный regex тихо перевёл бы их всех в fallback.
        $stillWorks = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Тара пэт 1л',
        ]));

        $this->assertSame('pet-tare-packages', $stillWorks);
    }

    /**
     * Правило accessory_title с пустым value отфильтровывается CategoryRegistry и не совпадает с произвольным
     * названием.
     */
    public function test_blank_accessory_keyword_is_rejected(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-blank-keyword',
            'code' => 'test_blank_keyword',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        $this->addMatchingRule([
            'slug' => $category->slug,
            'type' => CategoryMatchType::AccessoryTitle,
            'value' => null,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        // Товар с ключевым словом, которое реально существует в реестре
        // ('пэт'), всё ещё резолвится в правильную категорию — а не в
        // test-blank-keyword из-за пустой ветки.
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Тара пэт 1л',
        ]));

        $this->assertSame('pet-tare-packages', $slug);
    }

    public function test_contains_branch_is_checked_before_accessory_title(): void
    {
        // 'probes' (contains, needle 'пробник') должен побеждать даже если
        // верхний сегмент 'Сопутствующие товары' — ветка 2 идёт раньше ветки 3.
        $slug = app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары>Оборудование>Пробники',
            'abv' => '',
            'beer_style' => '',
            'name_full' => 'Набор пробников дегустационных',
        ]));

        $this->assertSame('probes', $slug);
    }

    public function test_accessory_uses_leftmost_match_even_when_priority_is_lower(): void
    {
        $this->assertSame('clothes', app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'Сопутствующие товары', 'name_full' => 'ШАПКА и пэт пакет',
        ])));
    }

    public function test_disabled_rule_is_not_used_and_equal_priorities_keep_list_order(): void
    {
        config(['category_matching.rules' => [
            ['slug' => 'beer', 'type' => 'contains', 'match_when' => null, 'value' => 'совпадение', 'priority' => 0, 'is_active' => false],
            ['slug' => 'mead', 'type' => 'contains', 'match_when' => null, 'value' => 'совпадение', 'priority' => 10, 'is_active' => true],
            ['slug' => 'cider', 'type' => 'contains', 'match_when' => null, 'value' => 'совпадение', 'priority' => 10, 'is_active' => true],
        ]]);
        $this->assertSame('mead', app(CategorySlugResolver::class)->resolve($this->row([
            'category' => 'СОВПАДЕНИЕ',
        ])));
    }
}
