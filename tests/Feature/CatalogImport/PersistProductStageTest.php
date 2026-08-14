<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
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

    public function test_updates_sync_fields_including_volume_on_existing_product(): void
    {
        $category = $this->category();
        $brand = Manufacturer::factory()->create();
        $oldVolume = Volume::factory()->create(['milliliters' => 450]);
        $newVolume = Volume::factory()->create(['milliliters' => 500]);
        $oldContainer = Container::factory()->create();
        $newContainer = Container::factory()->create();

        $existing = Product::factory()->create([
            'external_code' => 'EXIST1',
            'name' => 'Не должно измениться',
            'description' => 'Старое описание — create-only',
            'category_id' => $category->id,
            'manufacturer_id' => $brand->id,
            'volume_id' => $oldVolume->id,
            'container_id' => $oldContainer->id,
            'price' => 10,
            'stock_quantity' => 1,
            'in_stock' => true,
        ]);

        $ctx = $this->ctx(
            ['external_code' => 'EXIST1', 'name' => 'Новое имя из CSV', 'title' => 'Новый заголовок', 'article' => 'НОВЫЙ АРТИКУЛ', 'price' => 999, 'stock' => 0, 'pack_info' => 'кор. 12х0,5л ж/б'],
            $category,
            $brand,
        );
        $ctx->volume = $newVolume;
        $ctx->container = $newContainer;

        $result = $this->runStage($ctx);

        $this->assertFalse($result->product->wasRecentlyCreated);
        $existing->refresh();

        // Оперативные поля — как и раньше.
        $this->assertSame(999.0, $existing->price->major());
        $this->assertSame(0, $existing->stock_quantity);
        $this->assertFalse($existing->in_stock);

        // Новое: объёмный блок обновляется при повторном импорте.
        $this->assertSame($newVolume->id, $existing->volume_id);
        $this->assertSame($newContainer->id, $existing->container_id);
        $this->assertSame(12, $existing->package_units);
        $this->assertSame('кор. 12х0,5л ж/б', $existing->packaging_raw);

        // Новое: name/article тоже обновляются.
        $this->assertSame('Новый заголовок', $existing->name);
        $this->assertSame('НОВЫЙ АРТИКУЛ', $existing->article);

        // По-прежнему create-only.
        $this->assertSame('Старое описание — create-only', $existing->description);
    }

    public function test_strips_volume_mention_from_name_and_article_on_update(): void
    {
        $category = $this->category();
        $brand = Manufacturer::factory()->create();
        $volume = Volume::factory()->create(['milliliters' => 450]);

        $existing = Product::factory()->create([
            'external_code' => 'EXIST2',
            'category_id' => $category->id,
            'manufacturer_id' => $brand->id,
        ]);

        $ctx = $this->ctx(
            ['external_code' => 'EXIST2', 'name' => 'Марка', 'title' => 'Ортодокс "Тест" ж/б 0,45л', 'article' => 'ТЕСТ (ж/б 0,45л)', 'price' => 1, 'stock' => 1],
            $category,
            $brand,
        );
        $ctx->volume = $volume;

        $this->runStage($ctx);
        $existing->refresh();

        $this->assertSame('Ортодокс "Тест" ж/б', $existing->name);
        $this->assertSame('ТЕСТ (ж/б)', $existing->article);
    }

    public function test_name_falls_back_to_unstripped_text_when_stripping_leaves_nothing(): void
    {
        $ctx = $this->ctx(
            ['external_code' => 'ONLYVOL1', 'name' => 'Марка', 'title' => '0,45л', 'price' => 1, 'stock' => 1],
            $this->category(),
            Manufacturer::factory()->create(),
        );

        $result = $this->runStage($ctx);

        $this->assertSame('0,45л', $result->product->name);
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
        // Support\Casts\PurifiedHtml (см. tests/Feature/Domain/ProductDescriptionPurificationTest.php);
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
