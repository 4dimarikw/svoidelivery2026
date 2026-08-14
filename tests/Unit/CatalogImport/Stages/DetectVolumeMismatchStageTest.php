<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Volume;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\DetectVolumeMismatchStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class DetectVolumeMismatchStageTest extends TestCase
{
    use BuildsRawRows;

    private function runStage(array $row, int $packageMl): ImportContext
    {
        $ctx = new ImportContext($this->row($row));
        $ctx->volume = Volume::factory()->make(['milliliters' => $packageMl]);

        (new DetectVolumeMismatchStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_no_discrepancy_when_volumes_agree(): void
    {
        $ctx = $this->runStage([
            'package' => 'кор. 12х0,45л ж/б',
            'name_full' => 'Пиво "Тест" ж/б 0,45л',
            'article' => 'ТЕСТ (ж/б 0,45л)',
        ], 450);

        $this->assertArrayNotHasKey('volume_discrepancies', $ctx->attributes);
    }

    public function test_records_discrepancy_in_name(): void
    {
        $ctx = $this->runStage([
            'package' => 'кор. 12х0,5л ж/б',
            'name_full' => 'Ортодокс "Фестивальное" ж/б 0,45л',
            'article' => 'ФЕСТИВАЛЬНОЕ (ж/б 0,5л)',
        ], 500);

        $discrepancies = $ctx->attributes['volume_discrepancies'];
        $this->assertCount(1, $discrepancies);
        $this->assertSame('name', $discrepancies[0]['source']);
        $this->assertSame(500, $discrepancies[0]['package_ml']);
        $this->assertSame(450, $discrepancies[0]['text_ml']);
    }

    public function test_records_discrepancy_in_article(): void
    {
        $ctx = $this->runStage([
            'package' => 'кор. 12х0,5л ж/б',
            'name_full' => 'Ортодокс "Фестивальное" ж/б 0,5л',
            'article' => 'ФЕСТИВАЛЬНОЕ (ж/б 0,45л)',
        ], 500);

        $discrepancies = $ctx->attributes['volume_discrepancies'];
        $this->assertCount(1, $discrepancies);
        $this->assertSame('article', $discrepancies[0]['source']);
    }

    public function test_records_discrepancy_in_both(): void
    {
        $ctx = $this->runStage([
            'package' => 'кор. 12х0,5л ж/б',
            'name_full' => 'Ортодокс "Фестивальное" ж/б 0,45л',
            'article' => 'ФЕСТИВАЛЬНОЕ (ж/б 0,45л)',
        ], 500);

        $discrepancies = $ctx->attributes['volume_discrepancies'];
        $this->assertCount(2, $discrepancies);
        $this->assertSame(['name', 'article'], array_column($discrepancies, 'source'));
    }

    public function test_skips_when_package_volume_unresolved(): void
    {
        $ctx = new ImportContext($this->row([
            'package' => 'штучный товар',
            'name_full' => 'Пиво ж/б 0,45л',
            'article' => 'ТЕСТ (ж/б 0,45л)',
        ]));
        // $ctx->volume остаётся null — как если ResolveVolumeStage не смог распарсить Упаковку.

        (new DetectVolumeMismatchStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertArrayNotHasKey('volume_discrepancies', $ctx->attributes);
    }

    public function test_falls_back_to_tovar_column_when_name_full_is_empty(): void
    {
        $ctx = $this->runStage([
            'package' => 'кор. 12х0,5л ж/б',
            'name_full' => '',
            'product' => 'Ортодокс "Фестивальное" ж/б 0,45л',
            'article' => 'ФЕСТИВАЛЬНОЕ (ж/б 0,5л)',
        ], 500);

        $discrepancies = $ctx->attributes['volume_discrepancies'];
        $this->assertCount(1, $discrepancies);
        $this->assertSame('name', $discrepancies[0]['source']);
    }
}
