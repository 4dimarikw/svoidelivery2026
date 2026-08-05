<?php

namespace Tests\Unit\CatalogImport;

use Services\CatalogImport\Dto\RawRow;
use Tests\TestCase;

class RawRowTest extends TestCase
{
    public function test_get_trims_the_value(): void
    {
        $row = new RawRow(['Категория' => '  Пиво  '], 1);

        $this->assertSame('Пиво', $row->get('Категория'));
    }

    public function test_get_returns_default_for_missing_column(): void
    {
        $row = new RawRow(['Категория' => 'Пиво'], 1);

        $this->assertSame('', $row->get('НетТакойКолонки'));
        $this->assertSame('fallback', $row->get('НетТакойКолонки', 'fallback'));
    }

    public function test_get_on_empty_data_does_not_throw(): void
    {
        $row = new RawRow([], 5);

        $this->assertSame('', $row->get('Категория'));
        $this->assertSame(5, $row->lineNumber);
    }
}
