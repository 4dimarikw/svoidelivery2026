<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\ResolveVolumeStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class ResolveVolumeStageTest extends TestCase
{
    use BuildsRawRows;
    use RefreshDatabase;

    /** @var array<string, Category> Кэш категории на комбинацию expects_volume — чтобы повторные вызовы runStage() внутри одного теста не пытались создать вторую категорию с тем же уникальным code. */
    private array $categories = [];

    private function runStage(string $package, bool $expectsVolume = true): ImportContext
    {
        $key = $expectsVolume ? 'expects' : 'not-expects';

        $this->categories[$key] ??= Category::query()->create([
            'slug' => 'test-volume-cat-'.$key,
            'code' => 'test_volume_cat_'.$key,
            'name' => 'Тест',
            'is_active' => true,
            'expects_volume' => $expectsVolume,
        ]);

        $ctx = new ImportContext($this->row(['package' => $package]));
        $ctx->category = $this->categories[$key];

        (new ResolveVolumeStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_takes_the_last_match_not_the_box_count(): void
    {
        // "12х0,45л" — 12 это количество в коробке, а не объём; побеждает
        // последнее совпадение "л" — 0,45.
        $ctx = $this->runStage('кор. 12х0,45л ж/б');

        $this->assertNotNull($ctx->volume);
        $this->assertSame(450, $ctx->volume->milliliters);
        $this->assertSame('0.45', $ctx->volume->label);
    }

    public function test_liters_converted_and_rounded_to_milliliters(): void
    {
        $ctx = $this->runStage('пэт кег 20л');

        $this->assertSame(20000, $ctx->volume->milliliters);
        $this->assertSame('20', $ctx->volume->label);
    }

    public function test_firstorcreate_reuses_existing_volume_by_milliliters(): void
    {
        $first = $this->runStage('кор. 06х0,75л ст. бут.');
        $second = $this->runStage('другая упаковка 0,75л');

        $this->assertSame($first->volume->id, $second->volume->id);
    }

    public function test_warns_only_when_category_expects_volume(): void
    {
        $ctx = $this->runStage('Упаковка без объёма', expectsVolume: true);

        $this->assertNull($ctx->volume);
        $this->assertCount(1, $ctx->warnings);
        $this->assertSame('cannot parse volume', $ctx->warnings[0]['message']);
    }

    public function test_silent_when_category_does_not_expect_volume(): void
    {
        $ctx = $this->runStage('Упаковка без объёма', expectsVolume: false);

        $this->assertNull($ctx->volume);
        $this->assertSame([], $ctx->warnings);
    }
}
