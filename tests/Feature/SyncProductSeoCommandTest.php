<?php

namespace Tests\Feature;

use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Leeto\Seo\Models\Seo;
use Tests\TestCase;

class SyncProductSeoCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seoUrl(Product $product): string
    {
        return route('product.show', $product, absolute: false);
    }

    public function test_backfills_seo_for_a_product_missing_a_row(): void
    {
        // Product::factory()->create() уже создаёт seo-строку через
        // Product::booted() — удаляем её, имитируя товар, заведённый до
        // появления этой автоматики (ровно та ситуация, для которой нужна
        // команда: 888 существующих товаров без seo-строк).
        $product = Product::factory()->create(['brand' => 'Старый Товар']);
        Seo::where('url', $this->seoUrl($product))->delete();
        $this->assertDatabaseMissing('seo', ['url' => $this->seoUrl($product)]);

        $this->artisan('catalog:sync-seo')->assertExitCode(0);

        $this->assertDatabaseHas('seo', ['url' => $this->seoUrl($product)]);
        $this->assertStringContainsString('Старый Товар', Seo::where('url', $this->seoUrl($product))->value('title'));
    }

    public function test_ids_option_only_processes_the_given_products(): void
    {
        $wanted = Product::factory()->create();
        $other = Product::factory()->create();
        Seo::where('url', $this->seoUrl($wanted))->delete();
        Seo::where('url', $this->seoUrl($other))->delete();

        $this->artisan('catalog:sync-seo', ['--ids' => (string) $wanted->id])->assertExitCode(0);

        $this->assertDatabaseHas('seo', ['url' => $this->seoUrl($wanted)]);
        $this->assertDatabaseMissing('seo', ['url' => $this->seoUrl($other)]);
    }

    public function test_unknown_ids_fail_the_command(): void
    {
        $this->artisan('catalog:sync-seo', ['--ids' => '999999'])->assertExitCode(1);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $product = Product::factory()->create();
        Seo::where('url', $this->seoUrl($product))->delete();

        $this->artisan('catalog:sync-seo', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseMissing('seo', ['url' => $this->seoUrl($product)]);
    }

    public function test_command_does_not_trigger_n_plus_one_queries(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $volume = Volume::factory()->create();
        $container = Container::factory()->create();
        $beerStyle = BeerStyle::factory()->create();
        $untappdBeer = UntappdBeer::factory()->create();

        $products = Product::factory()->count(10)->create([
            'manufacturer_id' => $manufacturer->id,
            'volume_id' => $volume->id,
            'container_id' => $container->id,
        ]);

        $products->each(function (Product $product) use ($beerStyle, $untappdBeer): void {
            BeerProductDetail::factory()->create([
                'product_id' => $product->id,
                'beer_style_id' => $beerStyle->id,
                'untappd_beer_id' => $untappdBeer->id,
            ]);
            Seo::where('url', $this->seoUrl($product))->delete();
        });

        DB::enableQueryLog();
        $this->artisan('catalog:sync-seo', ['--chunk' => 200])->assertExitCode(0);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 10 товаров в одном чанке — если бы loadMissing() в Action грузил
        // связи по одному на каждый товар (без ->with() на уровне запроса),
        // это было бы 10*7=70+ запросов только на связи. Бюджет с запасом,
        // но ловит именно этот регресс.
        $this->assertLessThan(50, $queryCount, 'catalog:sync-seo не должна давать N+1 по товарам.');

        foreach ($products as $product) {
            $this->assertDatabaseHas('seo', ['url' => $this->seoUrl($product)]);
        }
    }
}
