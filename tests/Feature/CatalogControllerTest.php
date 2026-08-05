<?php

namespace Tests\Feature;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_header_and_footer(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('account.login.submit'));
    }

    public function test_mobile_filters_toggle_renders_with_alpine_bindings_intact(): void
    {
        // Регрессия: <x-ui.btn ::aria-expanded="filtersOpen"> — двойное двоеточие
        // обязательно, иначе Blade трактует "filtersOpen" как PHP-выражение
        // (константу) вместо буквенной Alpine-строки и падает с ошибкой рендера.
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="catalog-filters-panel"', false);
        $response->assertSee(':aria-expanded="filtersOpen"', false);
        $response->assertSee('aria-controls="catalog-filters-panel"', false);
        $response->assertSee('x-on:click="filtersOpen = !filtersOpen"', false);
        $response->assertSee('x-on:click="filtersOpen = false"', false);
    }

    public function test_select_filter_renders_with_alpine_bindings_intact(): void
    {
        // Регрессия того же класса бага: <x-ui.select-filter> использует
        // :aria-expanded="open" на НАТИВНОМ <button> (не <x-...> теге) — тут
        // одиночное двоеточие корректно, Blade его не трогает. Проверяем, что
        // в HTML буквально остаётся Alpine-выражение, а не PHP-константа/ошибка.
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('select-wrap', false);
        $response->assertSee(':aria-expanded="open"', false);
        $response->assertSee('x-on:click.outside="open = false"', false);
        $response->assertSee($category->name);
    }

    public function test_home_page_lists_published_in_stock_products(): void
    {
        $shown = Product::factory()->create(['name' => 'Тёмный лагер', 'status' => ProductStatus::PUBLISHED, 'in_stock' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($shown->name);
    }

    public function test_draft_and_archived_products_are_not_listed(): void
    {
        $draft = Product::factory()->create(['name' => 'Черновик Пиво', 'status' => ProductStatus::DRAFT]);
        $archived = Product::factory()->create(['name' => 'Архивное Пиво', 'status' => ProductStatus::ARCHIVED]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee($draft->name);
        $response->assertDontSee($archived->name);
    }

    public function test_category_filter_narrows_results(): void
    {
        $wantedCategory = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $wanted = Product::factory()->create(['name' => 'Хочу Это', 'category_id' => $wantedCategory->id]);
        $other = Product::factory()->create(['name' => 'Не Это', 'category_id' => $otherCategory->id]);

        $response = $this->get(route('home', ['categories' => [$wantedCategory->id]]));

        $response->assertOk();
        $response->assertSee($wanted->name);
        $response->assertDontSee($other->name);
    }

    public function test_price_range_filter_narrows_results(): void
    {
        $cheap = Product::factory()->create(['name' => 'Дешёвое', 'price' => 100]);
        $expensive = Product::factory()->create(['name' => 'Дорогое', 'price' => 4000]);

        $response = $this->get(route('home', ['price_min' => 500, 'price_max' => 5000]));

        $response->assertOk();
        $response->assertSee($expensive->name);
        $response->assertDontSee($cheap->name);
    }

    public function test_in_stock_filter_hides_out_of_stock_products(): void
    {
        $inStock = Product::factory()->create(['name' => 'В Наличии', 'in_stock' => true]);
        $outOfStock = Product::factory()->create(['name' => 'Нет В Наличии', 'in_stock' => false]);

        $response = $this->get(route('home', ['in_stock' => 1]));

        $response->assertOk();
        $response->assertSee($inStock->name);
        $response->assertDontSee($outOfStock->name);
    }

    public function test_search_filter_matches_product_name(): void
    {
        $match = Product::factory()->create(['name' => 'Хмельной эль']);
        $nonMatch = Product::factory()->create(['name' => 'Тёмный портер']);

        $response = $this->get(route('home', ['q' => 'Хмельн']));

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($nonMatch->name);
    }

    public function test_manufacturer_volume_and_container_filters_narrow_results(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $volume = Volume::factory()->create();
        $container = Container::factory()->create();

        $wanted = Product::factory()->create([
            'name' => 'Точное Совпадение',
            'manufacturer_id' => $manufacturer->id,
            'volume_id' => $volume->id,
            'container_id' => $container->id,
        ]);
        $other = Product::factory()->create(['name' => 'Другое']);

        $response = $this->get(route('home', [
            'manufacturers' => [$manufacturer->id],
            'volumes' => [$volume->id],
            'containers' => [$container->id],
        ]));

        $response->assertOk();
        $response->assertSee($wanted->name);
        $response->assertDontSee($other->name);
    }

    public function test_partial_request_returns_only_cards_with_next_page_header(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->get(route('home', ['page' => 2]), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $response->assertDontSee('</header>', false);
        $response->assertDontSee('</footer>', false);
    }

    public function test_next_page_header_is_empty_on_the_last_page(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->get(route('home'), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $this->assertSame('', $response->headers->get('X-Next-Page'));
    }

    public function test_next_page_header_carries_a_url_when_more_pages_exist(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->get(route('home'), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $nextPage = $response->headers->get('X-Next-Page');
        $this->assertNotSame('', $nextPage);
        $this->assertStringContainsString('page=2', $nextPage);
    }

    public function test_active_filters_are_preserved_in_the_next_page_url(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(30)->create(['category_id' => $category->id]);

        $response = $this->get(
            route('home', ['categories' => [$category->id]]),
            ['X-Catalog-Partial' => '1']
        );

        $response->assertOk();
        $this->assertStringContainsString('categories', urldecode($response->headers->get('X-Next-Page')));
    }

    public function test_invalid_category_filter_is_rejected_instead_of_500(): void
    {
        $response = $this->get(route('home', ['categories' => [999999]]));

        $response->assertInvalid(['categories.0']);
    }

    public function test_product_cards_do_not_trigger_n_plus_one_queries(): void
    {
        Manufacturer::factory()->count(3)->create()->each(function (Manufacturer $manufacturer): void {
            $volume = Volume::factory()->create();
            $container = Container::factory()->create();

            Product::factory()->count(5)->create([
                'manufacturer_id' => $manufacturer->id,
                'volume_id' => $volume->id,
                'container_id' => $container->id,
            ]);
        });

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Товары + справочники фильтров (категории/производители/объёмы/тара) —
        // фиксированное небольшое число запросов независимо от количества товаров.
        $this->assertLessThan(15, $queryCount, 'Ожидались фиксированные запросы без N+1 по manufacturer/volume/container.');
    }
}
