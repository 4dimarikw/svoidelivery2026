<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Actions\SyncProductSeoAction;
use Domain\Catalog\Actions\ZeroOutStaleProductsAction;
use Domain\Catalog\Models\Product;
use Domain\Logging\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Leeto\Seo\Models\Seo;
use Tests\Support\BuildsCatalogCsv;
use Tests\TestCase;

/**
 * catalog:import ставит ZeroOutStaleProductsJob в очередь после успешного
 * (не dry-run, без --categories) прогона; QUEUE_CONNECTION=sync в тестах
 * (phpunit.xml) исполняет его синхронно в том же запросе — отдельного
 * queue:work в тестах не требуется.
 */
class ZeroOutStaleProductsTest extends TestCase
{
    use BuildsCatalogCsv;
    use RefreshDatabase;

    private const SIX_CODES = ['A', 'B', 'C', 'D', 'E', 'F'];

    protected function tearDown(): void
    {
        $this->cleanupCatalogCsvTmpFiles();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $codes
     */
    private function writeSixCodesCsv(array $codes, array $overrides = []): string
    {
        return $this->writeCatalogCsv(array_map(
            fn (string $code) => $this->validCatalogRow(array_merge(['product_code' => $code, 'stock' => '10'], $overrides)),
            $codes,
        ));
    }

    public function test_product_missing_from_new_csv_is_zeroed_while_present_products_are_untouched(): void
    {
        // 6 товаров в наличии — пропажа одного (1/6 ≈ 16.7%) остаётся под
        // порогом по умолчанию (zero_out_max_percent = 20).
        $this->artisan('catalog:import', ['path' => $this->writeSixCodesCsv(self::SIX_CODES)])->assertExitCode(0);
        $this->assertSame(6, Product::query()->where('in_stock', true)->count());

        $this->artisan('catalog:import', ['path' => $this->writeSixCodesCsv(['B', 'C', 'D', 'E', 'F'])])->assertExitCode(0);

        $productA = Product::query()->firstWhere('external_code', 'A');
        $this->assertSame(0, $productA->stock_quantity);
        $this->assertFalse($productA->in_stock);

        foreach (['B', 'C', 'D', 'E', 'F'] as $code) {
            $product = Product::query()->firstWhere('external_code', $code);
            $this->assertSame(10, $product->stock_quantity);
            $this->assertTrue($product->in_stock);
        }
    }

    public function test_row_present_but_rejected_by_normalize_stage_is_not_zeroed(): void
    {
        $this->artisan('catalog:import', ['path' => $this->writeSixCodesCsv(self::SIX_CODES)])->assertExitCode(0);

        // 'A' по-прежнему есть в файле, но цена ниже catalog_import.normalize.min_price
        // (2) — NormalizeRowStage отбраковывает строку до PersistProductStage.
        // SeenCodeCollector::add() успевает сработать раньше пайплайна, так
        // что 'A' всё равно засчитывается как "виденный", а не пропавший.
        $rows = array_map(
            fn (string $code) => $this->validCatalogRow([
                'product_code' => $code,
                'stock' => '10',
                'price' => $code === 'A' ? '1' : '199.90',
            ]),
            self::SIX_CODES,
        );

        $this->artisan('catalog:import', ['path' => $this->writeCatalogCsv($rows)])->assertExitCode(0);

        $productA = Product::query()->firstWhere('external_code', 'A');
        $this->assertSame(10, $productA->stock_quantity);
        $this->assertTrue($productA->in_stock);
    }

    public function test_dry_run_does_not_track_seen_codes_or_zero_anything(): void
    {
        $productA = Product::factory()->create(['external_code' => 'A', 'stock_quantity' => 10, 'in_stock' => true]);

        // Сторожевая строка вместо "импортируем один раз и сверяем count" —
        // успешный (не dry-run) импорт сам truncate'ит catalog_import_seen_codes
        // на выходе (см. ZeroOutStaleProductsAction), так что count после
        // обычного прогона всегда 0 и не годится как база для сравнения.
        DB::table('catalog_import_seen_codes')->insert(['external_code' => 'SENTINEL']);

        $path = $this->writeSixCodesCsv(['B', 'C', 'D', 'E', 'F']);
        $this->artisan('catalog:import', ['path' => $path, '--dry-run' => true])->assertExitCode(0);

        // Ни SeenCodeCollector::reset()/add(), ни job не должны были запуститься.
        $this->assertSame(1, DB::table('catalog_import_seen_codes')->count());
        $this->assertDatabaseHas('catalog_import_seen_codes', ['external_code' => 'SENTINEL']);

        $productA->refresh();
        $this->assertSame(10, $productA->stock_quantity);
        $this->assertTrue($productA->in_stock);
    }

    public function test_categories_filter_disables_zero_out_entirely(): void
    {
        $rows = array_merge(
            array_map(fn (string $code) => $this->validCatalogRow(['product_code' => $code, 'stock' => '10']), self::SIX_CODES),
            [$this->validCatalogRow([
                'product_code' => 'ACCESSORY-X',
                'category' => 'Сопутствующие товары',
                'abv' => '',
                'name_full' => 'Тара пэт 1л',
                'stock' => '10',
            ])],
        );
        $this->artisan('catalog:import', ['path' => $this->writeCatalogCsv($rows)])->assertExitCode(0);

        // Полный импорт выше сам truncate'ит catalog_import_seen_codes на
        // успехе — сеем сторожевую строку, чтобы проверить, что частичный
        // импорт её не тронет (см. комментарий в предыдущем тесте).
        DB::table('catalog_import_seen_codes')->insert(['external_code' => 'SENTINEL']);

        // --categories=beer — частичный импорт, ACCESSORY-X и C..F вне
        // выборки не должны считаться пропавшими.
        $path = $this->writeSixCodesCsv(['A', 'B']);
        $this->artisan('catalog:import', ['path' => $path, '--categories' => 'beer'])->assertExitCode(0);

        $this->assertSame(1, DB::table('catalog_import_seen_codes')->count());
        $this->assertDatabaseHas('catalog_import_seen_codes', ['external_code' => 'SENTINEL']);

        foreach (['C', 'D', 'E', 'F'] as $code) {
            $product = Product::query()->firstWhere('external_code', $code);
            $this->assertSame(10, $product->stock_quantity);
            $this->assertTrue($product->in_stock);
        }

        $accessory = Product::query()->firstWhere('external_code', 'ACCESSORY-X');
        $this->assertSame(10, $accessory->stock_quantity);
        $this->assertTrue($accessory->in_stock);
    }

    public function test_threshold_exceeded_aborts_and_logs_a_warning_event(): void
    {
        $this->artisan('catalog:import', ['path' => $this->writeSixCodesCsv(self::SIX_CODES)])->assertExitCode(0);

        // Второй CSV не содержит ни одного из шести старых кодов — 6 из 6
        // товаров в наличии становятся "пропавшими" (100% > 20%).
        $path = $this->writeSixCodesCsv(['G', 'H']);
        $this->artisan('catalog:import', ['path' => $path])->assertExitCode(0);

        foreach (self::SIX_CODES as $code) {
            $product = Product::query()->firstWhere('external_code', $code);
            $this->assertSame(10, $product->stock_quantity);
            $this->assertTrue($product->in_stock);
        }

        // latest('id'), не firstOrFail() без сортировки — первый импорт (все
        // 6 кодов присутствуют) тоже логирует свой info-эвент с zeroed=0.
        $event = EventLog::query()->where('event_type', 'catalog_import.stale_products_zeroed')->latest('id')->firstOrFail();
        $this->assertSame('warning', $event->level);
        $this->assertSame('threshold_exceeded', $event->context['reason']);
    }

    public function test_action_is_idempotent_and_does_not_recount_already_zeroed_products(): void
    {
        // 9 товаров "виденных" в CSV — 1 пропавший укладывается в порог 20%.
        $seenProducts = Product::factory()->count(9)->create(['in_stock' => true, 'stock_quantity' => 5]);
        DB::table('catalog_import_seen_codes')->insert(
            $seenProducts->map(fn (Product $p) => ['external_code' => $p->external_code])->all()
        );

        $stale = Product::factory()->create(['external_code' => 'STALE-1', 'stock_quantity' => 5, 'in_stock' => true]);

        $result1 = app(ZeroOutStaleProductsAction::class)();

        $this->assertFalse($result1->aborted);
        $this->assertSame(1, $result1->zeroed);
        $this->assertSame(1, $result1->staleFound);

        $stale->refresh();
        $this->assertSame(0, $stale->stock_quantity);
        $this->assertFalse($stale->in_stock);

        // Успешный прогон очищает catalog_import_seen_codes — имитируем
        // следующий импорт, где STALE-1 по-прежнему отсутствует.
        $this->assertSame(0, DB::table('catalog_import_seen_codes')->count());
        DB::table('catalog_import_seen_codes')->insert(
            $seenProducts->map(fn (Product $p) => ['external_code' => $p->external_code])->all()
        );

        $result2 = app(ZeroOutStaleProductsAction::class)();

        // STALE-1 уже обнулён (in_stock=false, stock_quantity=0) — условие
        // "in_stock OR stock_quantity > 0" в ZeroOutStaleProductsAction его
        // больше не выбирает, второй прогон не пересчитывает его повторно.
        $this->assertFalse($result2->aborted);
        $this->assertSame(0, $result2->zeroed);
        $this->assertSame(0, $result2->staleFound);
    }

    public function test_zeroing_updates_json_ld_offer_availability_to_out_of_stock(): void
    {
        $seenProducts = Product::factory()->count(9)->create(['in_stock' => true, 'stock_quantity' => 5]);
        DB::table('catalog_import_seen_codes')->insert(
            $seenProducts->map(fn (Product $p) => ['external_code' => $p->external_code])->all()
        );

        $product = Product::factory()->create(['external_code' => 'STALE-SEO', 'in_stock' => true, 'stock_quantity' => 5]);
        // Product::booted() уже синкает seo на create(), но досинкаем явно —
        // тот же паттерн, что в Tests\Feature\Domain\ProductSeoTest.
        app(SyncProductSeoAction::class)($product->fresh());
        $url = route('product.show', $product, absolute: false);

        app(ZeroOutStaleProductsAction::class)();

        $seo = Seo::where('url', $url)->firstOrFail();
        $this->assertStringContainsString('https://schema.org/OutOfStock', $seo->text);
    }
}
