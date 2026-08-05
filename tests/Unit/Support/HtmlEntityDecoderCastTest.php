<?php

namespace Tests\Unit\Support;

use Domain\Catalog\Models\Product;
use Support\Casts\HtmlEntityDecoder;
use Tests\TestCase;

class HtmlEntityDecoderCastTest extends TestCase
{
    private function cast(): HtmlEntityDecoder
    {
        return new HtmlEntityDecoder;
    }

    public function test_get_decodes_html_entities(): void
    {
        $model = new Product;

        $this->assertSame('Пиво «Балтика»', $this->cast()->get($model, 'name', 'Пиво &laquo;Балтика&raquo;', []));
    }

    public function test_get_returns_null_for_non_string(): void
    {
        $model = new Product;

        $this->assertNull($this->cast()->get($model, 'name', null, []));
        $this->assertNull($this->cast()->get($model, 'name', 123, []));
    }

    public function test_set_trims_the_value(): void
    {
        $model = new Product;

        $this->assertSame('Пиво', $this->cast()->set($model, 'name', '  Пиво  ', []));
    }

    public function test_set_returns_null_for_non_string(): void
    {
        $model = new Product;

        $this->assertNull($this->cast()->set($model, 'name', null, []));
    }
}
