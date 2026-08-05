<?php

namespace Tests\Unit\CatalogImport\Stages;

use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\ResolveProductIdentityStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class ResolveProductIdentityStageTest extends TestCase
{
    use BuildsRawRows;
    use RefreshDatabase;

    private function runStage(array $row, ?Category $category = null): ImportContext
    {
        $ctx = new ImportContext($this->row($row));
        $ctx->category = $category;

        (new ResolveProductIdentityStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx;
    }

    public function test_name_comes_from_brand_column(): void
    {
        $ctx = $this->runStage(['brand' => 'Балтика 7', 'name_full' => 'Пиво Балтика 7 0.5л', 'product' => '', 'article' => '']);

        $this->assertFalse($ctx->skip);
        $this->assertSame('Балтика 7', $ctx->attributes['name']);
        $this->assertSame('Пиво Балтика 7 0.5л', $ctx->attributes['title']);
    }

    public function test_title_falls_back_to_product_column(): void
    {
        $ctx = $this->runStage(['brand' => 'Марка', 'name_full' => '', 'product' => 'Товар Х', 'article' => '']);

        $this->assertSame('Товар Х', $ctx->attributes['title']);
    }

    public function test_empty_brand_without_name_from_article_flag_skips(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-identity-no-flag',
            'code' => 'test_identity_no_flag',
            'name' => 'Тест',
            'is_active' => true,
            'name_from_article' => false,
        ]);

        $ctx = $this->runStage(['brand' => '', 'name_full' => 'Что-то', 'product' => '', 'article' => 'ART1'], $category);

        $this->assertTrue($ctx->skip);
        $this->assertStringContainsString('Марка', $ctx->warnings[0]['message']);
    }

    public function test_empty_brand_with_name_from_article_flag_uses_article(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-identity-flag',
            'code' => 'test_identity_flag',
            'name' => 'Тест',
            'is_active' => true,
            'name_from_article' => true,
        ]);

        $ctx = $this->runStage(['brand' => '', 'name_full' => 'Что-то', 'product' => '', 'article' => 'ART42'], $category);

        $this->assertFalse($ctx->skip);
        $this->assertSame('ART42', $ctx->attributes['name']);
    }

    public function test_empty_brand_with_name_from_article_flag_but_empty_article_still_skips(): void
    {
        $category = Category::query()->create([
            'slug' => 'test-identity-flag-empty-article',
            'code' => 'test_identity_flag_empty',
            'name' => 'Тест',
            'is_active' => true,
            'name_from_article' => true,
        ]);

        $ctx = $this->runStage(['brand' => '', 'name_full' => 'Что-то', 'product' => '', 'article' => ''], $category);

        $this->assertTrue($ctx->skip);
    }
}
