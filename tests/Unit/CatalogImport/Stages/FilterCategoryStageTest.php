<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Category;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Dto\RawRow;
use Services\CatalogImport\Stages\FilterCategoryStage;
use Tests\TestCase;

class FilterCategoryStageTest extends TestCase
{
    private function ctx(array $categoryFilter, ?string $slug): ImportContext
    {
        $ctx = new ImportContext(new RawRow([], 1), categoryFilter: $categoryFilter);
        if ($slug !== null) {
            $category = new Category(['slug' => $slug]);
            $category->exists = true;
            $ctx->category = $category;
        }

        return $ctx;
    }

    private function runStage(ImportContext $ctx): ImportContext
    {
        (new FilterCategoryStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_empty_filter_lets_everything_through(): void
    {
        $ctx = $this->runStage($this->ctx([], 'beer'));

        $this->assertFalse($ctx->skip);
    }

    public function test_slug_in_filter_passes(): void
    {
        $ctx = $this->runStage($this->ctx(['beer', 'mead'], 'beer'));

        $this->assertFalse($ctx->skip);
    }

    public function test_slug_outside_filter_is_skipped_without_warning(): void
    {
        $ctx = $this->runStage($this->ctx(['mead'], 'beer'));

        $this->assertTrue($ctx->skip);
        $this->assertSame([], $ctx->warnings);
    }

    public function test_null_category_with_active_filter_is_skipped(): void
    {
        $ctx = $this->runStage($this->ctx(['mead'], null));

        $this->assertTrue($ctx->skip);
    }
}
