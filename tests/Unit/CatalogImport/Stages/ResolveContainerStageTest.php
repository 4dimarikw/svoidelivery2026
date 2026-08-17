<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\LookupCache;
use Services\CatalogImport\Stages\ResolveContainerStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class ResolveContainerStageTest extends TestCase
{
    use BuildsRawRows;
    use RefreshDatabase;

    private function category(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'slug' => 'test-container-cat',
            'code' => 'test_container_cat',
            'name' => 'Тест',
            'is_active' => true,
            'expects_container' => true,
        ], $overrides));
    }

    private function runStage(ImportContext $ctx): ImportContext
    {
        (new ResolveContainerStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_skips_entirely_when_category_does_not_expect_container(): void
    {
        $ctx = new ImportContext($this->row(['package' => 'ж/б банка']));
        $ctx->category = $this->category(['expects_container' => false]);

        $result = $this->runStage($ctx);

        $this->assertNull($result->container);
        $this->assertSame([], $result->warnings);
    }

    public function test_fixed_container_code_ignores_package_column(): void
    {
        Container::query()->create(['code' => 'pet', 'name' => 'ПЭТ', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'ж/б банка (не важно)']));
        $ctx->category = $this->category(['container_code' => 'pet']);

        $result = $this->runStage($ctx);

        $this->assertNotNull($result->container);
        $this->assertSame('pet', $result->container->code);
    }

    public function test_container_map_order_pet_keg_before_pet(): void
    {
        // container_map объявляет 'пэт кег' раньше 'пэт' — более специфичная
        // подстрока должна выигрывать.
        Container::query()->create(['code' => 'pet_keg', 'name' => 'ПЭТ-кег', 'is_active' => true]);
        Container::query()->create(['code' => 'pet', 'name' => 'ПЭТ', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'пэт кег 20л']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame('pet_keg', $result->container->code);
    }

    public function test_unknown_package_warns(): void
    {
        $ctx = new ImportContext($this->row(['package' => 'совершенно неизвестная тара']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertNull($result->container);
        $this->assertSame('unknown container type', $result->warnings[0]['message']);
    }

    public function test_detected_code_not_seeded_warns(): void
    {
        // 'ж/б' матчится container_map'ом, но Container с code='can' не создан.
        $ctx = new ImportContext($this->row(['package' => 'ж/б банка']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertNull($result->container);
        $this->assertStringContainsString('not seeded', $result->warnings[0]['message']);
    }

    public function test_glass_bottle_detected_when_1c_omits_dot(): void
    {
        // Регрессия: 1С иногда шлёт "ст бут." без точки после "ст" —
        // раньше это проваливалось в общий маркер 'бут' => piece.
        Container::query()->create(['code' => 'glass_bottle', 'name' => 'Стеклянная бутылка', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'кор. 12х0,45л ст бут.']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame('glass_bottle', $result->container->code);
    }

    public function test_glass_bottle_still_detected_with_canonical_dots(): void
    {
        Container::query()->create(['code' => 'glass_bottle', 'name' => 'Стеклянная бутылка', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'кор. 12х0,33л ст. бут.']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame('glass_bottle', $result->container->code);
    }

    public function test_detection_is_case_insensitive(): void
    {
        Container::query()->create(['code' => 'glass_bottle', 'name' => 'Стеклянная бутылка', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'КОР. 12Х0,45Л СТ. БУТ.']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame('glass_bottle', $result->container->code);
    }

    public function test_bare_bottle_marker_falls_back_to_glass_bottle(): void
    {
        Container::query()->create(['code' => 'glass_bottle', 'name' => 'Стеклянная бутылка', 'is_active' => true]);

        $ctx = new ImportContext($this->row(['package' => 'бут 0,5л']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame('glass_bottle', $result->container->code);
    }

    public function test_piece_package_value_stays_unresolved(): void
    {
        // Осознанное решение: "штучный товар" в Упаковке означает отсутствие
        // тары, а не тип тары — container_id остаётся NULL, не 'piece'.
        $ctx = new ImportContext($this->row(['package' => 'штучный товар']));
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertNull($result->container);
        $this->assertSame('unknown container type', $result->warnings[0]['message']);
    }

    public function test_resolved_container_is_taken_from_lookup_cache_when_present(): void
    {
        Container::query()->create(['code' => 'can', 'name' => 'Банка (реальная)', 'is_active' => true]);

        $cached = new Container(['code' => 'can', 'name' => 'Банка (из кэша)', 'is_active' => true]);
        $cached->exists = true;
        $cached->id = 999999;

        $lookups = $this->createMock(LookupCache::class);
        $lookups->method('findContainerByCode')->with('can')->willReturn($cached);

        $ctx = new ImportContext($this->row(['package' => 'ж/б банка']), $lookups);
        $ctx->category = $this->category();

        $result = $this->runStage($ctx);

        $this->assertSame(999999, $result->container->id);
    }
}
