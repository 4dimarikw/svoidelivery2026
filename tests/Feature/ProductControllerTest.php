<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Sluggable\Exceptions\StaleSelfHealingUrl;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_page_is_visible(): void
    {
        $product = Product::factory()->create(['brand' => 'Жигулёвское']);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('Жигулёвское');
    }

    public function test_draft_product_page_is_not_found(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::DRAFT]);

        $response = $this->get(route('product.show', $product));

        $response->assertNotFound();
    }

    public function test_archived_product_page_is_not_found(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::ARCHIVED]);

        $response = $this->get(route('product.show', $product));

        $response->assertNotFound();
    }

    public function test_out_of_stock_product_page_is_still_visible(): void
    {
        // Каталог показывает такие товары (published(), не active()) —
        // страница товара должна вести себя так же.
        $product = Product::factory()->create(['in_stock' => false]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee(__('catalog.out_of_stock'));
    }

    public function test_stale_slug_redirects_to_canonical_url(): void
    {
        // HasSlug::getSlugOptions()->selfHealing() (Product.php) регенерирует
        // slug на update() по умолчанию — устаревший URL должен 308-редиректить
        // на новый, а не 404.
        $product = Product::factory()->create(['name' => 'Старое Название']);
        $staleUrl = route('product.show', $product);

        $product->update(['name' => 'Совсем Другое Название']);

        $response = $this->get($staleUrl);

        $response->assertStatus(308);
        $response->assertRedirect(route('product.show', $product->fresh()));
    }

    /**
     * StaleSelfHealingUrl — штатный flow control для редиректа (у него свой
     * render(), см. тест выше), не ошибка — bootstrap/app.php регистрирует
     * его в dontReport(), иначе каждый заход по устаревшей ссылке писал бы
     * ложный ERROR в лог.
     */
    public function test_stale_slug_exception_is_not_reported(): void
    {
        $product = Product::factory()->create();
        $exception = new StaleSelfHealingUrl($product, 'stale-slug');

        $this->assertFalse($this->app->make(ExceptionHandler::class)->shouldReport($exception));
    }

    public function test_guest_does_not_see_the_price(): void
    {
        $product = Product::factory()->create(['price' => 480]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertDontSee(__('catalog.buy'));
        $response->assertDontSee((string) $product->price, false);
    }

    public function test_authenticated_user_sees_price_and_buy_button(): void
    {
        $product = Product::factory()->create(['price' => 480, 'in_stock' => true, 'stock_quantity' => 5]);

        $response = $this->actingAs(User::factory()->create())->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee(__('catalog.buy'));
        $response->assertSee((string) $product->price, false);
    }

    public function test_beer_specs_render_with_compact_number_format(): void
    {
        $product = Product::factory()->create();

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'abv' => 5.8,
            'ibu' => 40,
            'plato' => 12.5,
            'ebc' => 20,
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('5,8%', false);
        $response->assertSee('40 IBU');
        $response->assertSee('12,5 °P', false);
        $response->assertSee('20 EBC');
    }

    public function test_non_beer_product_omits_beer_specs(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertDontSee('EBC');
        $response->assertDontSee('°P', false);
    }

    public function test_untappd_link_shown_when_linked(): void
    {
        // Относительный путь — реальный формат, который пишет
        // ResolveBeerStyleStage; UntappdBeer::url() обязан отдать
        // абсолютный https://untappd.com/... URL на чтении.
        $product = Product::factory()->create();
        $untappdBeer = UntappdBeer::factory()->create(['url' => '/b/salden-hop-machine/123']);

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'untappd_beer_id' => $untappdBeer->id,
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee(__('catalog.product.untappd_link'));
        $response->assertSee('https://untappd.com/b/salden-hop-machine/123', false);
    }

    public function test_untappd_link_hidden_without_link(): void
    {
        $product = Product::factory()->create();

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'untappd_beer_id' => null,
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertDontSee(__('catalog.product.untappd_link'));
    }

    public function test_untappd_description_is_not_rendered(): void
    {
        // untappdBeer->description дублирует products.description и
        // приходит на английском — сознательно не выводится, см. комментарий
        // в pages/product.blade.php.
        $product = Product::factory()->create();
        $untappdBeer = UntappdBeer::factory()->create(['description' => 'Some English tasting notes blurb']);

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'untappd_beer_id' => $untappdBeer->id,
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertDontSee('Some English tasting notes blurb');
    }

    public function test_similar_products_match_by_beer_style_and_exclude_self(): void
    {
        $style = BeerStyle::factory()->create();
        $otherStyle = BeerStyle::factory()->create();

        $product = Product::factory()->create(['brand' => 'Основной']);
        BeerProductDetail::factory()->create(['product_id' => $product->id, 'beer_style_id' => $style->id]);

        $sameStyle = Product::factory()->create(['brand' => 'Такой Же Стиль']);
        BeerProductDetail::factory()->create(['product_id' => $sameStyle->id, 'beer_style_id' => $style->id]);

        $differentStyle = Product::factory()->create(['brand' => 'Другой Стиль']);
        BeerProductDetail::factory()->create(['product_id' => $differentStyle->id, 'beer_style_id' => $otherStyle->id]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('Такой Же Стиль');
        $response->assertDontSee('Другой Стиль');
        // Собственное название товара легитимно есть на странице (H1,
        // крошки) — самоисключение проверяем по данным вьюхи, не по тексту.
        $response->assertViewHas('similar', fn ($similar) => ! $similar->contains('id', $product->id));
    }

    public function test_similar_products_fall_back_to_category_without_beer_style(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $product = Product::factory()->create(['category_id' => $category->id]);
        Product::factory()->create(['category_id' => $category->id, 'brand' => 'Та Же Категория']);
        Product::factory()->create(['category_id' => $otherCategory->id, 'brand' => 'Другая Категория']);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('Та Же Категория');
        $response->assertDontSee('Другая Категория');
    }

    public function test_similar_products_are_limited_to_five(): void
    {
        $style = BeerStyle::factory()->create();

        $product = Product::factory()->create();
        BeerProductDetail::factory()->create(['product_id' => $product->id, 'beer_style_id' => $style->id]);

        Product::factory()->count(7)->create()->each(function (Product $sibling) use ($style): void {
            BeerProductDetail::factory()->create(['product_id' => $sibling->id, 'beer_style_id' => $style->id]);
        });

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertViewHas('similar', fn ($similar) => $similar->count() === 5);
    }

    public function test_page_renders_manufacturer_volume_and_container(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Балтика']);
        $volume = Volume::factory()->create(['label' => '0,5 л']);
        $container = Container::factory()->create(['name' => 'Стеклянная бутылка', 'label' => null]);

        $product = Product::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'volume_id' => $volume->id,
            'container_id' => $container->id,
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('Балтика');
        $response->assertSee('0,5 л');
        $response->assertSee('Стеклянная бутылка');
    }

    public function test_product_page_does_not_trigger_n_plus_one_queries(): void
    {
        $style = BeerStyle::factory()->create();

        $product = Product::factory()->create();
        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'beer_style_id' => $style->id,
            'untappd_beer_id' => UntappdBeer::factory()->create()->id,
        ]);

        Product::factory()->count(5)->create()->each(function (Product $sibling) use ($style): void {
            BeerProductDetail::factory()->create(['product_id' => $sibling->id, 'beer_style_id' => $style->id]);
        });

        DB::enableQueryLog();
        $this->actingAs(User::factory()->create())->get(route('product.show', $product))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Бюджет выше, чем у каталога (CatalogControllerTest, 15): здесь два
        // независимых раунда eager-load вместо одного — сам товар и похожие
        // товары грузятся (и догружают связи) отдельными запросами. Порог
        // всё равно должен ловить настоящий N+1 — по запросу на каждый из
        // 6 похожих товаров это было бы far выше 25.
        $this->assertLessThan(25, $queryCount, 'Страница товара не должна давать N+1 по похожим товарам.');
    }
}
