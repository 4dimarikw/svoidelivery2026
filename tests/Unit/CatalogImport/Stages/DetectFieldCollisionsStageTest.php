<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Product;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\DetectFieldCollisionsStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class DetectFieldCollisionsStageTest extends TestCase
{
    use BuildsRawRows;

    public function test_normalization_fallback_empty_values_and_threshold(): void
    {
        foreach ([
            ['brand' => 'Ёж-Лимон 0,45л', 'name_full' => 'Напиток ЕЖ ЛИМОН, 0.5л'],
            ['brand' => 'Лимон', 'name_full' => 'Другой текст', 'article' => 'ЛИМОН (0,45л)'],
            ['brand' => 'Лимон', 'product' => 'Напиток Лимон'],
            ['brand' => '', 'name_full' => 'Напиток'],
            ['brand' => 'Лимон'],
        ] as $row) {
            $ctx = new ImportContext($this->row($row));
            $ctx->category = new Category(['slug' => 'non-alcoholic']);
            $ctx->attributes['abv'] = 0.5;
            $ctx->product = new Product(['external_code' => 'TEST']);
            $ctx->product->setRelation('category', $ctx->category);
            $ctx->product->setRelation('beerDetails', new BeerProductDetail(['abv' => 0.5]));
            $before = $ctx->product->getAttributes();
            (new DetectFieldCollisionsStage)($ctx, fn ($c) => $c);
            $this->assertSame([], $ctx->fieldCollisions);
            $this->assertFalse($ctx->skip);
            $this->assertSame($before, $ctx->product->getAttributes());
        }
    }

    public function test_packaging_name_without_path_marker_and_multiple_reasons(): void
    {
        $ctx = new ImportContext($this->row(['name_full' => 'Коробка MAN', 'brand' => 'Туристическая', 'category' => 'Алкогольная продукция']));
        $ctx->category = new Category(['slug' => 'non-alcoholic']);
        $ctx->attributes['abv'] = 5.6;
        $ctx->product = new Product(['external_code' => 'TEST']);
        $ctx->product->setRelation('category', $ctx->category);
        $ctx->product->setRelation('beerDetails', new BeerProductDetail(['abv' => 5.6]));
        $stage = new DetectFieldCollisionsStage;
        $stage($ctx, fn ($c) => $c);
        $stage($ctx, fn ($c) => $c);
        $this->assertSame(['category_abv', 'category_packaging', 'name_mismatch'], array_keys($ctx->fieldCollisions));
    }
}
