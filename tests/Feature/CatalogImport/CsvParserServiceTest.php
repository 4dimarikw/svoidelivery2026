<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\CsvParserService;
use Services\CatalogImport\Dto\ImportOptions;
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

    public function test_reimport_updates_only_sync_fields_not_admin_edited_ones(): void
    {
        $code = 'CODE-IDEMPOTENT';
        $path = $this->writeCatalogCsv([$this->validCatalogRow(['product_code' => $code, 'price' => '100', 'stock' => '10'])]);
        $this->import($path);

        $product = Product::query()->firstWhere('external_code', $code);
        $product->update(['name' => 'Название, изменённое админом']);

        // Повторный импорт: цена/остаток другие в CSV.
        $path2 = $this->writeCatalogCsv([$this->validCatalogRow(['product_code' => $code, 'price' => '250', 'stock' => '0'])]);
        $report = $this->import($path2);

        $product->refresh();

        $this->assertSame(0, $report->productsCreated);
        $this->assertSame(1, $report->productsUpdated);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(250.0, $product->price->major());
        $this->assertSame(0, $product->stock_quantity);
        $this->assertFalse($product->in_stock);
        // name — create-only поле, повторный импорт не должен его затирать.
        $this->assertSame('Название, изменённое админом', $product->name);
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
