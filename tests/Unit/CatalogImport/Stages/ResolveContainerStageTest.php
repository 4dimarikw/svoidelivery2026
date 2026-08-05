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
