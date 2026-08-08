<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Dto\RawRow;
use Services\CatalogImport\Stages\PersistProductStage;
use Tests\TestCase;

class PersistProductStageTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(array $attributes, ?Category $category, ?Manufacturer $brand): ImportContext
    {
        $ctx = new ImportContext(new RawRow([], 1));
        $ctx->category = $category;
        $ctx->brand = $brand;
        $ctx->attributes = $attributes;

        return $ctx;
    }

    private function category(): Category
    {
        return Category::query()->create([
            'slug' => 'test-persist-cat',
            'code' => 'test_persist_cat',
            'name' => 'Тест',
            'is_active' => true,
        ]);
    }

    private function runStage(ImportContext $ctx): ImportContext
    {
        app(PersistProductStage::class)->__invoke($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_skips_without_brand_or_category(): void
    {
        $ctx = $this->ctx(['external_code' => 'X1', 'name' => 'N', 'price' => 1, 'stock' => 1], null, null);
        $result = $this->runStage($ctx);

        $this->assertTrue($result->skip);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_skips_with_warning_on_empty_external_code(): void
    {
        $ctx = $this->ctx(
            ['external_code' => '', 'name' => 'N', 'price' => 1, 'stock' => 1],
            $this->category(),
            Manufacturer::factory()->create(),
        );

        $result = $this->runStage($ctx);

        $this->assertTrue($result->skip);
        $this->assertStringContainsString('external_code', $result->warnings[0]['message']);
    }

    public function test_creates_a_new_product(): void
    {
        $ctx = $this->ctx(
            [
                'external_code' => 'NEW1',
                'article' => 'ART1',
                'name' => 'Бренд',
                'title' => 'Полное название',
                'price' => 150.5,
                'stock' => 20,
                'pack_info' => 'кор. 12х0,5л',
            ],
            $this->category(),
            Manufacturer::factory()->create(),
        );

        $result = $this->runStage($ctx);

        $this->assertFalse($result->skip);
        $this->assertNotNull($result->product);
        $this->assertTrue($result->product->wasRecentlyCreated);
        $this->assertSame('Полное название', $result->product->name);
        $this->assertSame(150.5, $result->product->price->major());
        $this->assertSame(20, $result->product->stock_quantity);
        $this->assertTrue($result->product->in_stock);
    }

    public function test_updates_only_sync_fields_on_existing_product(): void
    {
        $category = $this->category();
        $brand = Manufacturer::factory()->create();

        $existing = Product::factory()->create([
            'external_code' => 'EXIST1',
            'name' => 'Не должно измениться',
            'category_id' => $category->id,
            'manufacturer_id' => $brand->id,
            'price' => 10,
            'stock_quantity' => 1,
            'in_stock' => true,
        ]);

        $ctx = $this->ctx(
            ['external_code' => 'EXIST1', 'name' => 'Новое имя из CSV', 'title' => 'Новый заголовок', 'price' => 999, 'stock' => 0],
            $category,
            $brand,
        );

        $result = $this->runStage($ctx);

        $this->assertFalse($result->product->wasRecentlyCreated);
        $existing->refresh();
        $this->assertSame(999.0, $existing->price->major());
        $this->assertSame(0, $existing->stock_quantity);
        $this->assertFalse($existing->in_stock);
        // name — create-only, повторный импорт его не трогает.
        $this->assertSame('Не должно измениться', $existing->name);
    }

    public static function packagingProvider(): array
    {
        return [
            'кор. 12х0,45л' => ['кор. 12х0,45л ж/б', 12],
            'Упаковка 6 шт.' => ['Упаковка 6 шт.', 6],
            'без совпадения' => ['произвольная строка', null],
            'пусто' => [null, null],
        ];
    }

    #[DataProvider('packagingProvider')]
    public function test_parses_package_units_heuristically(?string $packageRaw, ?int $expectedUnits): void
    {
        $ctx = $this->ctx(
            [
                'external_code' => 'PKG-'.uniqid(),
                'name' => 'N',
                'price' => 1,
                'stock' => 1,
                'pack_info' => $packageRaw,
            ],
            $this->category(),
            Manufacturer::factory()->create(),
        );

        $result = $this->runStage($ctx);

        $this->assertSame($expectedUnits, $result->product->package_units);
    }

    public function test_untappd_description_takes_priority_over_csv_description(): void
    {
        $beer = UntappdBeer::factory()->create(['description' => 'Untappd-описание']);

        $ctx = $this->ctx(
            ['external_code' => 'UT1', 'name' => 'N', 'price' => 1, 'stock' => 1, 'description' => 'CSV-описание'],
            $this->category(),
            Manufacturer::factory()->create(),
        );
        $ctx->untappdBeer = $beer;

        $result = $this->runStage($ctx);

        // containsString, не assertSame — description теперь идёт через
        // Support\Casts\PurifiedHtml (AutoFormat.AutoParagraph оборачивает
        // голый текст в <p>, см. tests/Feature/Domain/ProductDescriptionPurificationTest.php);
        // здесь важен только источник (Untappd, не CSV), не точное форматирование.
        $this->assertStringContainsString('Untappd-описание', $result->product->description);
        $this->assertStringNotContainsString('CSV-описание', $result->product->description);
    }

    public function test_flags_default_to_empty_array_when_not_set(): void
    {
        $ctx = $this->ctx(
            ['external_code' => 'NOFLAGS1', 'name' => 'N', 'price' => 1, 'stock' => 1],
            $this->category(),
            Manufacturer::factory()->create(),
        );

        $result = $this->runStage($ctx);

        $this->assertSame([], $result->product->flags);
    }
}
