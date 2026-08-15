<?php

namespace Tests\Unit\CatalogImport\Stages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\ResolveDescriptionStage;
use Services\CatalogImport\Stages\ResolveEbcStage;
use Services\CatalogImport\Stages\ResolveExternalIdsStage;
use Services\CatalogImport\Stages\ResolveFlagsStage;
use Services\CatalogImport\Stages\ResolveIbuStage;
use Services\CatalogImport\Stages\ResolvePlatoStage;
use Services\CatalogImport\Stages\ResolvePriceStage;
use Services\CatalogImport\Stages\ResolveShelfLifeStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

/**
 * Мелкие однострочные стейджи (парсинг одной колонки без ветвления по
 * категории) — сгруппированы в один файл, чтобы не плодить 7 почти пустых
 * тестовых классов.
 */
class ScalarStagesTest extends TestCase
{
    use BuildsRawRows;
    use RefreshDatabase;

    public static function intColumnProvider(): array
    {
        return [
            'ibu numeric' => [ResolveIbuStage::class, 'ibu', '35', 'ibu', 35],
            'ibu empty' => [ResolveIbuStage::class, 'ibu', '', 'ibu', null],
            'ibu garbage' => [ResolveIbuStage::class, 'ibu', 'н/д', 'ibu', null],
            'ebc numeric' => [ResolveEbcStage::class, 'ebc', '12', 'ebc', 12],
            'ebc empty' => [ResolveEbcStage::class, 'ebc', '', 'ebc', null],
            'shelf_life numeric' => [ResolveShelfLifeStage::class, 'shelf_life', '180', 'shelf_life_days', 180],
            'shelf_life empty' => [ResolveShelfLifeStage::class, 'shelf_life', '', 'shelf_life_days', null],
        ];
    }

    #[DataProvider('intColumnProvider')]
    public function test_int_column_stage(string $stageClass, string $column, string $raw, string $attributeKey, ?int $expected): void
    {
        $ctx = new ImportContext($this->row([$column => $raw]));
        (new $stageClass)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame($expected, $ctx->attributes[$attributeKey]);
    }

    public function test_plato_normalizes_comma_to_dot(): void
    {
        $ctx = new ImportContext($this->row(['plato' => '12,5']));
        (new ResolvePlatoStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame(12.5, $ctx->attributes['plato']);
    }

    public function test_plato_empty_is_null(): void
    {
        $ctx = new ImportContext($this->row(['plato' => '']));
        (new ResolvePlatoStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertNull($ctx->attributes['plato']);
    }

    public function test_price_and_stock_parsed_together(): void
    {
        $ctx = new ImportContext($this->row(['price' => '1 234,56', 'stock' => '10']));
        (new ResolvePriceStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame(1234.56, $ctx->attributes['price']);
        $this->assertSame(10, $ctx->attributes['stock']);
    }

    public function test_price_unparseable_defaults_to_zero_not_null(): void
    {
        // Явно 0.0/0, а не null — иначе нарушится NOT NULL на products.price/stock_quantity.
        $ctx = new ImportContext($this->row(['price' => 'н/д', 'stock' => 'н/д']));
        (new ResolvePriceStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame(0.0, $ctx->attributes['price']);
        $this->assertSame(0, $ctx->attributes['stock']);
    }

    public function test_external_ids_maps_all_three_columns(): void
    {
        $ctx = new ImportContext($this->row([
            'product_code' => 'CODE1',
            'article' => 'ART1',
            'package' => 'кор. 12х0,5л',
        ]));
        (new ResolveExternalIdsStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame('CODE1', $ctx->attributes['external_code']);
        $this->assertSame('ART1', $ctx->attributes['article']);
        $this->assertSame('кор. 12х0,5л', $ctx->attributes['pack_info']);
    }

    public function test_external_ids_empty_article_and_package_become_null(): void
    {
        $ctx = new ImportContext($this->row(['product_code' => 'CODE1', 'article' => '', 'package' => '']));
        (new ResolveExternalIdsStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertNull($ctx->attributes['article']);
        $this->assertNull($ctx->attributes['pack_info']);
    }

    public function test_description_empty_string_becomes_null(): void
    {
        $ctx = new ImportContext($this->row(['description' => '']));
        (new ResolveDescriptionStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertNull($ctx->attributes['description']);
    }

    public function test_description_non_empty_is_kept(): void
    {
        $ctx = new ImportContext($this->row(['description' => 'Хмельное пиво']));
        (new ResolveDescriptionStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertSame('Хмельное пиво', $ctx->attributes['description']);
    }

    public function test_flags_marks_promo_from_sales_rating_marker(): void
    {
        $ctx = new ImportContext($this->row(['sales_rating' => 'Акция']));
        (new ResolveFlagsStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertTrue($ctx->attributes['flags']['promo']);
        // wu — true когда товар не сматчен с Untappd (untappdBeer не задан в этом тесте).
        $this->assertTrue($ctx->attributes['flags']['wu']);
        $this->assertFalse($ctx->attributes['flags']['mss']);
        // fil стейдж не переопределяет — значение приходит из config('catalog_import.flags.defaults').
        $this->assertFalse($ctx->attributes['flags']['fil']);
    }

    public function test_flags_promo_false_when_rating_does_not_match_marker(): void
    {
        $ctx = new ImportContext($this->row(['sales_rating' => 'Обычный']));
        (new ResolveFlagsStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertFalse($ctx->attributes['flags']['promo']);
    }
}
