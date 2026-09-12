<?php

namespace Tests\Feature\CatalogImport;

use App\Events\CatalogFieldCollisionsDetected;
use App\MoonShine\Resources\EventLog\EventLogResource;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Product;
use Domain\Logging\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Infrastructure\Settings\EventLoggingSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Services\CatalogImport\CsvParserService;
use Services\CatalogImport\Dto\ImportOptions;
use Services\CatalogImport\ImportReportLogger;
use Tests\Support\BuildsCatalogCsv;
use Tests\TestCase;

class FieldCollisionsTest extends TestCase
{
    use BuildsCatalogCsv;
    use RefreshDatabase;

    private array $reports = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->cleanupCatalogCsvTmpFiles();
        foreach ($this->reports as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    private function cider(array $overrides = []): array
    {
        return $this->validCatalogRow(array_merge([
            'product_code' => 'CIDER-COLLISION',
            'category' => 'Алкогольная продукция>Cider House>Double Tree бут',
            'name_full' => 'Cider House Double Tree Черная Смородина (Cider), алк. 5.6%',
            'brand' => 'Double Tree Черная Смородина',
            'article' => 'Double Tree Черная Смородина',
            'abv' => '',
        ], $overrides));
    }

    public function test_cider_is_logged_once_without_changing_imported_values(): void
    {
        $path = $this->writeCatalogCsv([$this->cider()]);
        $report = app(CsvParserService::class)->import($path);
        $product = Product::where('external_code', 'CIDER-COLLISION')->sole();
        $this->assertSame(1, $report->productsCreated);
        $this->assertSame(0, $report->skipped);
        $this->assertSame('non-alcoholic', $product->category->slug);
        $this->assertEquals(5.6, $product->beerDetails->abv);
        $this->assertSame(1, $report->fieldCollisionsTotal);
        $collision = $report->fieldCollisions[0];
        $this->assertSame('category_abv', $collision['reason']);
        $this->assertSame(['resolved', 'saved'], $collision['sources']);
        $this->assertSame(2, $collision['line']);
        $this->assertSame($product->id, $collision['product_id']);
        $this->assertSame('', $collision['fields']['ABV']);
        $event = EventLog::where('event_type', 'catalog_import.field_collisions_detected')->sole();
        $this->assertSame('warning', $event->level);
        $this->assertSame(1, $event->context['count']);
        $this->assertSame('CIDER-COLLISION', $event->context['collisions'][0]['external_code']);
        $this->assertEquals($collision['fields'], $event->context['collisions'][0]['fields']);
        $admin = MoonshineUser::create([
            'email' => 'collision-audit@test.local', 'password' => bcrypt('password'),
            'name' => 'Проверка журнала', 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($admin, 'moonshine')
            ->get(app(EventLogResource::class)->getDetailPageUrl($event->id))
            ->assertOk()->assertSee('CIDER-COLLISION')->assertSee('category_abv');
    }

    public function test_boxes_and_name_mismatch_have_separate_reasons_in_one_event(): void
    {
        $rows = [];
        foreach (['MAN', 'WOMAN'] as $variant) {
            $rows[] = $this->cider([
                'product_code' => 'BOX-'.$variant,
                'category' => 'Алкогольная продукция>SVOI>Подарочные короба',
                'name_full' => 'Короб Адвент-календарь на 6 вложений '.$variant,
                'article' => 'Короб Адвент-календарь на 6 вложений '.$variant,
                'brand' => $variant === 'MAN' ? 'Коробка Туристическая на 6 вложений' : 'Короб Адвент-календарь на 6 вложений WOMAN',
            ]);
        }
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv($rows));
        $this->assertSame(2, $report->productsCreated);
        $this->assertSame(0, $report->skipped);
        $this->assertSame(['category_packaging', 'name_mismatch', 'category_packaging'], array_column($report->fieldCollisions, 'reason'));
        $this->assertStringContainsString('Возможное несоответствие — требуется проверка', $report->fieldCollisions[1]['message']);
        $this->assertSame(1, EventLog::where('event_type', 'catalog_import.field_collisions_detected')->count());
    }

    public function test_reimport_audits_saved_and_resolved_values_without_overwriting_them(): void
    {
        $importer = app(CsvParserService::class);
        $importer->import($this->writeCatalogCsv([$this->cider()]));
        $product = Product::where('external_code', 'CIDER-COLLISION')->sole();
        $report = $importer->import($this->writeCatalogCsv([$this->cider(['abv' => '0.5', 'beer_style' => 'Cider'])]));
        $this->assertSame(['saved'], $report->fieldCollisions[0]['sources']);
        $this->assertSame('cider', $report->fieldCollisions[0]['values']['resolved']['category']);
        $this->assertSame('non-alcoholic', $product->fresh()->category->slug);
        $this->assertEquals(5.6, $product->fresh()->beerDetails->abv);
        $this->assertSame(0, $report->productsCreated);
        $this->assertSame(1, Product::count());

        $product->update(['category_id' => Category::where('slug', 'cider')->sole()->id]);
        $report = $importer->import($this->writeCatalogCsv([$this->cider()]));
        $this->assertSame(['resolved'], $report->fieldCollisions[0]['sources']);
        $this->assertSame('cider', $product->fresh()->category->slug);
    }

    public function test_dry_run_keeps_collisions_and_file_but_no_products_or_event(): void
    {
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv([$this->cider()]), new ImportOptions(dryRun: true));
        $this->assertSame(1, $report->fieldCollisionsTotal);
        $this->assertSame(0, Product::count());
        $this->assertSame(0, EventLog::where('event_type', 'catalog_import.field_collisions_detected')->count());
        $path = app(ImportReportLogger::class)->writeSuccess(['Файл' => 'fixture.csv'], $report, true);
        $this->reports[] = $path;
        $text = file_get_contents($path);
        $this->assertStringContainsString('Коллизии полей товара (1)', $text);
        $this->assertStringContainsString('CIDER-COLLISION', $text);
        $this->assertStringContainsString('"ABV":""', $text);
        $this->assertStringContainsString('Double Tree Черная Смородина', $text);
    }

    public function test_limits_are_independent_and_truncation_is_visible(): void
    {
        $rows = [$this->cider(), $this->cider(['product_code' => 'SECOND'])];
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv($rows), new ImportOptions(warningLimit: 1));
        $report->addWarnings(99, [['stage' => 'test', 'message' => 'test', 'value' => 'test']], 1);
        $this->assertSame(2, $report->fieldCollisionsTotal);
        $this->assertCount(1, $report->fieldCollisions);
        $this->assertCount(1, $report->warnings);
        $event = EventLog::where('event_type', 'catalog_import.field_collisions_detected')->sole();
        $this->assertSame(2, $event->context['count']);
        $this->assertSame(1, $event->context['omitted_count']);
        $path = app(ImportReportLogger::class)->writeSuccess(['Файл' => 'fixture.csv'], $report, false);
        $this->reports[] = $path;
        $this->assertStringContainsString('ещё 1 не сохранено', file_get_contents($path));
    }

    public function test_event_can_be_disabled_without_losing_file_diagnostics(): void
    {
        $settings = app(EventLoggingSettings::class);
        $settings->disabled_event_types = ['catalog_import.field_collisions_detected'];
        $settings->save();
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv([$this->cider()]));
        $this->assertSame(1, $report->fieldCollisionsTotal);
        $this->assertSame(0, EventLog::where('event_type', 'catalog_import.field_collisions_detected')->count());
        $path = app(ImportReportLogger::class)->writeSuccess(['Файл' => 'fixture.csv'], $report, false);
        $this->reports[] = $path;
        $this->assertStringContainsString('CIDER-COLLISION', file_get_contents($path));
    }

    public function test_zero_limit_keeps_total_and_emits_summary(): void
    {
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv([$this->cider()]), new ImportOptions(warningLimit: 0, transactionMode: 'chunk', chunkSize: 2));
        $this->assertSame(1, $report->fieldCollisionsTotal);
        $this->assertSame([], $report->fieldCollisions);
        $event = EventLog::where('event_type', 'catalog_import.field_collisions_detected')->sole();
        $this->assertSame(1, $event->context['omitted_count']);
        $this->assertSame([], $event->context['collisions']);
    }

    public function test_clean_rows_and_skipped_rows_do_not_emit_collision_event(): void
    {
        Event::fake([CatalogFieldCollisionsDetected::class]);
        $report = app(CsvParserService::class)->import($this->writeCatalogCsv([
            $this->validCatalogRow(['brand' => 'Пиво светлое']),
            $this->cider(['price' => '0']),
        ]));
        $this->assertSame(1, $report->productsCreated);
        $this->assertSame(1, $report->skipped);
        $this->assertSame(0, $report->fieldCollisionsTotal);
        Event::assertNotDispatched(CatalogFieldCollisionsDetected::class);
    }
}
