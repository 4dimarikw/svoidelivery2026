<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\NormalizeRowStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class NormalizeRowStageTest extends TestCase
{
    use BuildsRawRows;
    use RefreshDatabase;

    private function baseRow(array $overrides = []): array
    {
        return array_merge([
            'object_id' => '1',
            'product_code' => 'CODE1',
            'article' => '',
            'price' => '100',
            'category' => 'Пиво',
            'package' => 'кор. 12х0,5л ж/б',
        ], $overrides);
    }

    private function runStage(array $row, ?Category $category = null): ImportContext
    {
        $ctx = new ImportContext($this->row($row));
        $ctx->category = $category;

        (new NormalizeRowStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_happy_path_is_not_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow());

        $this->assertFalse($ctx->skip);
    }

    public function test_empty_object_id_is_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow(['object_id' => '']));

        $this->assertTrue($ctx->skip);
        $this->assertStringContainsString('id_объекта', $ctx->warnings[0]['message']);
    }

    public function test_missing_both_identifiers_is_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow(['product_code' => '', 'article' => '']));

        $this->assertTrue($ctx->skip);
    }

    public function test_only_article_present_is_enough(): void
    {
        $ctx = $this->runStage($this->baseRow(['product_code' => '', 'article' => 'ART1']));

        $this->assertFalse($ctx->skip);
    }

    public function test_price_below_minimum_is_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow(['price' => '1']));

        $this->assertTrue($ctx->skip);
        $this->assertStringContainsString('цена', $ctx->warnings[0]['message']);
    }

    public function test_price_exempt_category_bypasses_minimum_price(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-price-exempt',
            'code' => 'test_price_exempt',
            'name' => 'Тест',
            'is_active' => true,
            'price_exempt' => true,
        ]);

        $ctx = $this->runStage($this->baseRow(['price' => '1']), $category);

        $this->assertFalse($ctx->skip);
    }

    public function test_excluded_category_is_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow(['category' => 'Архив']));

        $this->assertTrue($ctx->skip);
        $this->assertStringContainsString('категория', $ctx->warnings[0]['message']);
    }

    public function test_excluded_package_is_skipped(): void
    {
        $ctx = $this->runStage($this->baseRow(['package' => 'пэт кег 30л']));

        $this->assertTrue($ctx->skip);
        $this->assertStringContainsString('упаковка', $ctx->warnings[0]['message']);
    }

    public function test_price_with_comma_and_thousands_separator_is_parsed(): void
    {
        // "1 234,50" → 1234.50, выше минимума — строка не должна быть skip'нута.
        $ctx = $this->runStage($this->baseRow(['price' => '1 234,50']));

        $this->assertFalse($ctx->skip);
    }
}
