<?php

namespace Tests\Unit;

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
}
