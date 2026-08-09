<?php

namespace Tests\Feature;

use Domain\Catalog\Filters\FilterOptionsRegistry;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FilterOptionsRegistry кэширует значения витринных справочных фильтров
 * (categories/beer_styles/manufacturers/volumes/containers) целиком одним
 * ключом — раньше каждый AbstractFilter::values() бил в БД при каждом
 * рендере каталога. Правки в админке должны попадать в следующий же рендер,
 * без ручного сброса кэша (по образцу CategoryRegistryCacheTest).
 */
class FilterOptionsRegistryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_container_invalidates_the_cache(): void
    {
        $container = Container::factory()->create(['name' => 'Тара', 'label' => 'Исходная', 'is_active' => true]);

        $this->assertSame('Исходная', FilterOptionsRegistry::for('containers')[$container->id]);

        $container->update(['label' => 'Обновлённая']);

        $this->assertSame('Обновлённая', FilterOptionsRegistry::for('containers')[$container->id]);
    }

    public function test_container_without_a_label_falls_back_to_name(): void
    {
        // Регресс на mapWithKeys(label ?: name) — раньше это была логика
        // внутри ContainerFilter::values(), теперь перенесена в load().
        $container = Container::factory()->create(['name' => 'Только имя', 'label' => '', 'is_active' => true]);

        $this->assertSame('Только имя', FilterOptionsRegistry::for('containers')[$container->id]);
    }

    public function test_saving_a_category_invalidates_the_filter_options_cache(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-filter-cache-category',
            'code' => 'test_filter_cache_category',
            'name' => 'Категория',
            'is_active' => true,
        ]);

        $this->assertSame('Категория', FilterOptionsRegistry::for('categories')[$category->id]);

        $category->update(['name' => 'Категория обновлена']);

        $this->assertSame('Категория обновлена', FilterOptionsRegistry::for('categories')[$category->id]);
    }

    public function test_saving_a_beer_style_invalidates_the_cache(): void
    {
        $style = BeerStyle::factory()->create(['name' => 'Стиль', 'is_active' => true]);

        $this->assertSame('Стиль', FilterOptionsRegistry::for('beer_styles')[$style->id]);

        $style->update(['name' => 'Стиль обновлён']);

        $this->assertSame('Стиль обновлён', FilterOptionsRegistry::for('beer_styles')[$style->id]);
    }

    public function test_saving_a_manufacturer_invalidates_the_cache(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Производитель', 'is_active' => true]);

        $this->assertSame('Производитель', FilterOptionsRegistry::for('manufacturers')[$manufacturer->id]);

        $manufacturer->update(['name' => 'Производитель обновлён']);

        $this->assertSame('Производитель обновлён', FilterOptionsRegistry::for('manufacturers')[$manufacturer->id]);
    }

    public function test_saving_a_volume_invalidates_the_cache(): void
    {
        $volume = Volume::factory()->create(['label' => '0.5 л']);

        $this->assertSame('0.5 л', FilterOptionsRegistry::for('volumes')[$volume->id]);

        $volume->update(['label' => '0.5 л обновлено']);

        $this->assertSame('0.5 л обновлено', FilterOptionsRegistry::for('volumes')[$volume->id]);
    }

    public function test_deleting_a_container_invalidates_the_cache(): void
    {
        $container = Container::factory()->create(['is_active' => true]);

        $this->assertArrayHasKey($container->id, FilterOptionsRegistry::for('containers'));

        $container->delete();

        $this->assertArrayNotHasKey($container->id, FilterOptionsRegistry::for('containers'));
    }

    public function test_second_call_within_the_same_request_does_not_hit_the_database(): void
    {
        Container::factory()->create(['is_active' => true]);
        FilterOptionsRegistry::flush();

        FilterOptionsRegistry::for('containers');

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        FilterOptionsRegistry::for('containers');
        FilterOptionsRegistry::for('categories');

        $this->assertSame(0, $queries);
    }
}
