<?php

namespace Tests\Feature\Domain;

use Domain\Catalog\Actions\SyncProductSeoAction;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Leeto\Seo\Models\Seo;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeFullBeerProduct(array $overrides = []): Product
    {
        $category = Category::factory()->create(['name' => 'Пиво']);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Балтика']);
        $volume = Volume::factory()->create(['label' => '0,5 л']);
        $container = Container::factory()->create(['name' => 'Стеклянная бутылка', 'label' => 'ст. бут.']);
        $beerStyle = BeerStyle::factory()->create(['name' => 'IPA']);
        $untappdBeer = UntappdBeer::factory()->create(['style' => 'Other']);

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'volume_id' => $volume->id,
            'container_id' => $container->id,
            'brand' => 'Жигулёвское',
            'price' => 5680,
            'packaging_raw' => null,
        ], $overrides));

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'beer_style_id' => $beerStyle->id,
            'untappd_beer_id' => $untappdBeer->id,
            'abv' => 5.8,
        ]);

        // PersistProductStage создаёт Product раньше, чем PersistBeerDetailsStage
        // добавляет beerDetails (см. известное ограничение в Product::booted()) —
        // BeerProductDetail::create() выше не трогает Product, поэтому хук не
        // перезапускается сам. Здесь тестируется вывод шаблона на полностью
        // собранном товаре, а не тайминг триггера — досинкаем явно.
        app(SyncProductSeoAction::class)($product->fresh());

        return $product->fresh();
    }

    private function jsonLd(Seo $seo): array
    {
        preg_match('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $seo->text, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script block not found in seo.text');

        $decoded = json_decode($matches[1], true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD is not valid JSON: '.json_last_error_msg());

        return $decoded;
    }

    public function test_creating_a_product_fills_title_description_keywords_and_text(): void
    {
        $product = $this->makeFullBeerProduct();

        $seo = Seo::where('url', route('product.show', $product, absolute: false))->firstOrFail();

        $this->assertSame(
            "Пиво 'Балтика' Жигулёвское 0,5 л ст. бут.",
            $seo->title,
        );
        $this->assertSame(
            'Пиво Балтика Жигулёвское - IPA - Other, 5.80% алк., 0,5 л. . Закажите онлайн!',
            $seo->description,
        );
        $this->assertSame(
            'Балтика, Жигулёвское, крафтовое пиво с доставкой',
            $seo->keywords,
        );

        $this->assertStringNotContainsString('og:locale', $seo->text);
        $this->assertStringContainsString('property="og:title" content="'.htmlspecialchars($seo->title, ENT_QUOTES, 'UTF-8').'"', $seo->text);
        $this->assertStringContainsString('property="og:site_name" content="'.config('app.name').'"', $seo->text);

        $jsonLd = $this->jsonLd($seo);
        $this->assertSame('Product', $jsonLd['@type']);
        $this->assertSame('Балтика Жигулёвское 0,5 л, ст. бут.', $jsonLd['name']);
        $this->assertSame('Жигулёвское', $jsonLd['brand']['name']);
        $this->assertArrayNotHasKey('priceCurrency', $jsonLd['offers']);
        $this->assertArrayNotHasKey('price', $jsonLd['offers']);
        $this->assertSame('https://schema.org/InStock', $jsonLd['offers']['availability']);
        $this->assertSame(route('product.show', $product), $jsonLd['offers']['url']);
    }

    public function test_changing_brand_updates_the_seo_title_in_place(): void
    {
        // brand не участвует в генерации slug (только name/article) —
        // url остаётся прежним, строка обновляется на месте, не дублируется.
        $product = Product::factory()->create(['brand' => 'Old Brand']);
        $url = route('product.show', $product, absolute: false);

        $product->update(['brand' => 'New Brand']);

        $this->assertSame(1, Seo::where('url', $url)->count());
        $this->assertStringContainsString('New Brand', Seo::where('url', $url)->value('title'));
    }

    public function test_changing_name_moves_the_seo_row_to_the_new_url(): void
    {
        // brand=null — title и slug оба опираются на name, чтобы реально
        // сдвинуть self-healing url (Product::getSlugOptions()).
        $product = Product::factory()->create(['name' => 'Старое Название', 'brand' => null]);
        $oldUrl = route('product.show', $product, absolute: false);

        $product->update(['name' => 'Новое Название']);
        $product->refresh();
        $newUrl = route('product.show', $product, absolute: false);

        $this->assertNotSame($oldUrl, $newUrl);
        $this->assertDatabaseMissing('seo', ['url' => $oldUrl]);
        $this->assertStringContainsString('Новое Название', Seo::where('url', $newUrl)->value('title'));
    }

    public function test_changing_price_does_not_touch_seo(): void
    {
        // Цена больше не входит ни в один SEO-текст (магазин не занимается
        // оптом, цену и «купить» из шаблона убрали) — реальное изменение
        // цены не должно трогать seo, как и в обычном price/stock-ре-импорте.
        $product = Product::factory()->create(['price' => 100]);
        $seoId = Seo::where('url', route('product.show', $product, absolute: false))->value('id');

        $product->update(['price' => 12345]);

        $this->assertSame(1, Seo::count());
        $this->assertSame($seoId, Seo::first()->id);
    }

    public function test_changing_stock_status_updates_availability(): void
    {
        // in_stock всё ещё наблюдается (Product::SEO_WATCHED_ATTRIBUTES) —
        // влияет на JSON-LD Offer.availability, в отличие от price.
        $product = Product::factory()->create(['in_stock' => true]);
        $url = route('product.show', $product, absolute: false);

        $product->update(['in_stock' => false]);

        $seo = Seo::where('url', $url)->firstOrFail();
        $this->assertSame('https://schema.org/OutOfStock', $this->jsonLd($seo)['offers']['availability']);
    }

    public function test_resaving_identical_values_does_not_touch_seo(): void
    {
        // Тот самый паттерн PersistProductStage (catalog:import) — ре-импорт
        // гоняет update() по всему каталогу на каждый прогон. wasChanged()
        // сравнивает значения, не факт наличия ключа в update() — те же
        // price/stock_quantity/in_stock повторно не должны трогать seo.
        $product = Product::factory()->create([
            'price' => 480, 'stock_quantity' => 10, 'in_stock' => true,
        ]);
        $seoId = Seo::where('url', route('product.show', $product, absolute: false))->value('id');

        $product->update(['price' => 480, 'stock_quantity' => 10, 'in_stock' => true]);

        $this->assertSame(1, Seo::count());
        $this->assertSame($seoId, Seo::first()->id);
    }

    public function test_text_escapes_html_and_json_breaking_characters(): void
    {
        $product = Product::factory()->create([
            'brand' => 'Evil</script><script>alert(1)</script>"Beer',
        ]);

        $seo = Seo::where('url', route('product.show', $product, absolute: false))->firstOrFail();

        // Ни один буквальный </script> не должен встретиться вне ровно
        // одной пары <script type="application/ld+json">...</script> —
        // иначе злое значение вырывается из JSON-LD блока в HTML документа.
        $this->assertSame(1, substr_count($seo->text, '<script type="application/ld+json">'));
        $this->assertSame(1, substr_count($seo->text, '</script>'));

        // JSON-LD остаётся валидным JSON несмотря на спецсимволы в значении.
        $jsonLd = $this->jsonLd($seo);
        $this->assertStringContainsString('Evil</script><script>alert(1)</script>"Beer', $jsonLd['brand']['name']);
    }

    public function test_product_without_beer_details_omits_beer_specific_fields(): void
    {
        $category = Category::factory()->create(['name' => 'Аксессуары']);
        $manufacturer = Manufacturer::factory()->create(['name' => 'NoName Co']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'brand' => 'Аксессуар',
            'price' => 300,
            'packaging_raw' => null,
        ]);

        $seo = Seo::where('url', route('product.show', $product, absolute: false))->firstOrFail();

        $this->assertSame("Аксессуары 'NoName Co' Аксессуар", $seo->title);
        $this->assertSame('Аксессуары NoName Co Аксессуар. . Закажите онлайн!', $seo->description);
        $this->assertStringNotContainsString('алк.', $seo->description);

        $jsonLd = $this->jsonLd($seo);
        $this->assertSame('Product', $jsonLd['@type']);
        $this->assertArrayNotHasKey('image', $jsonLd);
    }

    public function test_deleting_a_product_removes_its_seo_row(): void
    {
        $product = Product::factory()->create();
        $url = route('product.show', $product, absolute: false);
        $this->assertDatabaseHas('seo', ['url' => $url]);

        $product->delete();

        $this->assertDatabaseMissing('seo', ['url' => $url]);
    }
}
