<?php

namespace Tests\Unit;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\CategorySlugResolver;
use Services\CatalogImport\Dto\RawRow;
use Tests\TestCase;

/**
 * Прогоняет все 4 ветки CategorySlugResolver (alcohol/contains/
 * accessory_title/fallback) — регрессия против переезда реестра из
 * config/catalog_import.php в БД. Не сеет фикстуры вручную: migration
 * 2026_08_03_100200_seed_category_registry_from_config уже транспланти-
 * рует ровно тот реестр, что раньше жил в конфиге, и RefreshDatabase
 * прогоняет её на каждый тестовый прогон — так тест заодно проверяет,
 * что смигрированные данные резолвят slug идентично старому конфигу.
 */
class CategorySlugResolverTest extends TestCase
{
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
     * Регрессия: admin-редактируемое ключевое слово с "/" раньше ломало
     * альтернацию (preg_quote() без делимитера не экранирует "/") — см.
     * CategorySlugResolver::resolveAccessoryTitleSlug().
     */
    public function test_accessory_keyword_containing_slash_does_not_break_the_pattern(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-slash-keyword',
            'code' => 'test_slash_keyword',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        CategoryMatchRule::query()->create([
            'category_id' => $category->id,
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
     * Регрессия: правило accessory_title с пустым value раньше собиралось в
     * keywords => [null] → пустая ветка альтернации → матчит offset 0 любого
     * названия. CategoryRegistry теперь отфильтровывает такие правила.
     */
    public function test_blank_accessory_keyword_does_not_match_everything(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-blank-keyword',
            'code' => 'test_blank_keyword',
            'name' => 'Тест',
            'is_active' => true,
        ]);

        CategoryMatchRule::query()->create([
            'category_id' => $category->id,
            'type' => CategoryMatchType::AccessoryTitle,
            'value' => null,
            'priority' => 1,
            'is_active' => true,
        ]);

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
}
