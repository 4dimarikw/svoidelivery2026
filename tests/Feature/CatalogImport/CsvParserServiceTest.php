<?php

namespace Tests\Feature\CatalogImport;

use App\Events\CatalogImportPriceCoerced;
use App\Events\CatalogImportRowsSkipped;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Services\CatalogImport\CsvParserService;
use Services\CatalogImport\Dto\ImportOptions;
use Services\CatalogImport\Stages\ResolveProductIdentityStage;
use Tests\Support\BuildsCatalogCsv;
use Tests\TestCase;

/**
 * Сквозной прогон пайплайна через реальную БД (мигрированный реестр категорий,
 * см. migration 2026_08_03_100200_seed_category_registry_from_config). Ни одна
 * строка фикстуры не задаёт UntappdRef — ResolveBeerStyleStage не делает HTTP.
 */
class CsvParserServiceTest extends TestCase
{
    use BuildsCatalogCsv;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanupCatalogCsvTmpFiles();

        parent::tearDown();
    }

    private function import(string $path, ?ImportOptions $options = null)
    {
        return app(CsvParserService::class)->import($path, $options);
    }

    public function test_report_counters_reflect_the_fixture(): void
    {
        $path = $this->writeCatalogCsv([
            $this->validCatalogRow(['product_code' => 'CODE-A']),
            $this->validCatalogRow(['product_code' => 'CODE-B', 'manufacturer' => 'Другой Пивзавод']),
        ]);

        $report = $this->import($path);

        $this->assertSame(2, $report->processed);
        $this->assertSame(0, $report->skipped);
        $this->assertSame(0, $report->malformedRows);
        $this->assertSame(2, $report->productsCreated);
        $this->assertSame(0, $report->productsUpdated);
        $this->assertSame(2, Product::query()->count());
    }

    public function test_reimport_updates_sync_fields_including_name_but_not_description(): void
    {
        $code = 'CODE-IDEMPOTENT';
        $path = $this->writeCatalogCsv([$this->validCatalogRow(['product_code' => $code, 'price' => '100', 'stock' => '10'])]);
        $this->import($path);

        $product = Product::query()->firstWhere('external_code', $code);
        // description — по-прежнему create-only, повторный импорт не должен
        // его трогать, в отличие от name (см. ниже).
        $product->update(['description' => 'Описание, изменённое админом']);

        // Повторный импорт: цена/остаток другие в CSV, name_full тот же (из
        // fixture) — если бы админ поправил name вручную, следующий импорт
        // всё равно вернул бы значение из CSV (осознанный трейд-офф ради
        // синхронизации объёма, см. PersistProductStage).
        $path2 = $this->writeCatalogCsv([$this->validCatalogRow(['product_code' => $code, 'price' => '250', 'stock' => '0'])]);
        $report = $this->import($path2);

        $product->refresh();

        $this->assertSame(0, $report->productsCreated);
        $this->assertSame(1, $report->productsUpdated);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(250.0, $product->price->major());
        $this->assertSame(0, $product->stock_quantity);
        $this->assertFalse($product->in_stock);
        // name — теперь sync-поле, повторный импорт освежает его из CSV.
        $this->assertSame('Пиво светлое тестовое', $product->name);
        // description — create-only, правка админа сохранена.
        $this->assertSame('Описание, изменённое админом', $product->description);
    }

    public function test_dry_run_rolls_back_everything(): void
    {
        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);

        $report = $this->import($path, new ImportOptions(dryRun: true));

        $this->assertSame(1, $report->productsCreated);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_malformed_rows_are_counted_and_skipped(): void
    {
        $header = implode(';', array_map(
            fn (string $key) => config('catalog_import.columns.'.$key),
            array_keys(config('catalog_import.columns'))
        ));

        $goodRow = $this->validCatalogRow();
        $goodLine = implode(';', array_map(fn (string $key) => $goodRow[$key] ?? '', array_keys(config('catalog_import.columns'))));

        $path = $this->writeRawCatalogCsv($header."\n".$goodLine."\nтолько_одна_колонка\n");

        $report = $this->import($path);

        $this->assertSame(1, $report->malformedRows);
        $this->assertSame(1, $report->processed);
        $this->assertSame(1, $report->productsCreated);
    }

    public function test_category_filter_skips_other_categories(): void
    {
        $path = $this->writeCatalogCsv([
            $this->validCatalogRow(['product_code' => 'CODE-BEER']),
            $this->validCatalogRow([
                'product_code' => 'CODE-ACCESSORY',
                'category' => 'Сопутствующие товары',
                'abv' => '',
                'name_full' => 'Тара пэт 1л',
            ]),
        ]);

        $report = $this->import($path, new ImportOptions(categoryFilter: ['beer']));

        $this->assertSame(1, $report->productsCreated);
        $this->assertSame(1, $report->skipped);
        $this->assertNotNull(Product::query()->firstWhere('external_code', 'CODE-BEER'));
        $this->assertNull(Product::query()->firstWhere('external_code', 'CODE-ACCESSORY'));
    }

    public function test_warning_limit_caps_stored_warnings_but_total_keeps_growing(): void
    {
        // Container 'can' сидируется, чтобы каждая строка не добавляла свой
        // собственный "not seeded" warning от ResolveContainerStage (он идёт
        // раньше ResolveProductIdentityStage в config('catalog_import.stages'))
        // — иначе warningsTotal считал бы по 2 на строку, а не по 1.
        Container::query()->create(['code' => 'can', 'name' => 'Банка', 'is_active' => true]);

        // Каждая строка с пустой "Марка" у не-name_from_article категории
        // выдаёт по одному warning + skip (ResolveProductIdentityStage).
        $rows = array_map(
            fn (int $i) => $this->validCatalogRow(['product_code' => "CODE-W{$i}", 'brand' => '']),
            range(1, 3),
        );
        $path = $this->writeCatalogCsv($rows);

        $report = $this->import($path, new ImportOptions(warningLimit: 1));

        $this->assertSame(3, $report->warningsTotal);
        $this->assertCount(1, $report->warnings);
    }

    /**
     * Раньше пропуски строк были видны только в консоли/файле отчёта —
     * CatalogImportRowsSkipped агрегирует их в одно событие на прогон
     * (не на строку, иначе построчный цикл завалил бы event_logs).
     */
    public function test_skipped_rows_fire_one_aggregated_event_with_a_breakdown_by_stage(): void
    {
        Event::fake([CatalogImportRowsSkipped::class]);

        // Пустая "Марка" у категории без name_from_article — ResolveProductIdentityStage
        // отдаёт skip (см. её докблок).
        $rows = array_map(
            fn (int $i) => $this->validCatalogRow(['product_code' => "CODE-SKIP{$i}", 'brand' => '']),
            range(1, 2),
        );
        $path = $this->writeCatalogCsv($rows);

        $report = $this->import($path);

        $this->assertSame(2, $report->skipped);
        $this->assertSame(2, $report->skippedByStage[ResolveProductIdentityStage::class] ?? 0);

        Event::assertDispatched(CatalogImportRowsSkipped::class, function (CatalogImportRowsSkipped $event): bool {
            $this->assertSame(2, $event->skippedTotal);
            $this->assertSame(0, $event->malformedRows);
            $this->assertSame(2, $event->byStage[ResolveProductIdentityStage::class] ?? 0);

            return true;
        });
    }

    public function test_no_skip_event_when_nothing_was_skipped(): void
    {
        Event::fake([CatalogImportRowsSkipped::class]);

        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);
        $this->import($path);

        Event::assertNotDispatched(CatalogImportRowsSkipped::class);
    }

    /**
     * ResolvePriceStage приводит нечисловые Цену/Остаток к 0 без явного
     * сигнала — прямой денежный эффект (товар остаётся в каталоге с нулевой
     * ценой). CatalogImportPriceCoerced делает это заметным в event_logs.
     *
     * price_exempt=true обходит NormalizeRowStage::priceBelowMinimum() —
     * та парсит Цену тем же способом (non-numeric → 0.0) и без этого исключила
     * бы строку раньше, чем она дойдёт до ResolvePriceStage.
     */
    public function test_non_numeric_price_and_stock_fire_price_coerced_event(): void
    {
        Event::fake([CatalogImportPriceCoerced::class]);
        Category::query()->where('slug', 'beer')->update(['price_exempt' => true]);

        $path = $this->writeCatalogCsv([
            $this->validCatalogRow(['product_code' => 'CODE-BADPRICE', 'price' => 'н/д', 'stock' => 'abc']),
        ]);

        $this->import($path);

        $product = Product::query()->firstWhere('external_code', 'CODE-BADPRICE');
        $this->assertSame(0.0, $product->price->major());
        $this->assertSame(0, $product->stock_quantity);

        Event::assertDispatched(CatalogImportPriceCoerced::class, function (CatalogImportPriceCoerced $event): bool {
            $this->assertSame(1, $event->coercedPriceCount);
            $this->assertSame(1, $event->coercedStockCount);

            return true;
        });
    }

    public function test_no_price_coerced_event_when_price_and_stock_are_numeric(): void
    {
        Event::fake([CatalogImportPriceCoerced::class]);

        $path = $this->writeCatalogCsv([$this->validCatalogRow(['price' => '100', 'stock' => '5'])]);
        $this->import($path);

        Event::assertNotDispatched(CatalogImportPriceCoerced::class);
    }

    public function test_chunk_transaction_mode_gives_same_result_as_row_mode(): void
    {
        $rowsData = array_map(
            fn (int $i) => $this->validCatalogRow(['product_code' => "CODE-CHUNK{$i}"]),
            range(1, 3),
        );

        $path = $this->writeCatalogCsv($rowsData);
        $report = $this->import($path, new ImportOptions(transactionMode: 'chunk', chunkSize: 2));

        $this->assertSame(3, $report->productsCreated);
        $this->assertSame(3, Product::query()->count());
    }
}
