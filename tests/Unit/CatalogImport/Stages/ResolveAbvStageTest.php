<?php

namespace Tests\Unit\CatalogImport\Stages;

use PHPUnit\Framework\Attributes\DataProvider;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Stages\ResolveAbvStage;
use Tests\Support\BuildsRawRows;
use Tests\TestCase;

class ResolveAbvStageTest extends TestCase
{
    use BuildsRawRows;

    private function abv(array $data): ?float
    {
        $ctx = new ImportContext($this->row($data));
        (new ResolveAbvStage)($ctx, fn (ImportContext $c) => $c);

        return $ctx->attributes['abv'];
    }

    /**
     * Все 12 месяцев Excel-мусора: "день.месяц_рус" → день.номер_месяца.
     */
    public static function excelMonthsProvider(): array
    {
        return [
            'январь' => ['01.янв', 1.1],
            'февраль' => ['04.фев', 4.2],
            'март' => ['02.мар', 2.3],
            'апрель' => ['06.апр', 6.4],
            'май' => ['05.май', 5.5],
            'июнь' => ['03.июн', 3.6],
            'июль' => ['07.июл', 7.7],
            'август' => ['08.авг', 8.8],
            'сентябрь' => ['09.сен', 9.9],
            'октябрь' => ['10.окт', 10.1],
            'ноябрь' => ['11.ноя', 11.11],
            'декабрь' => ['12.дек', 12.12],
        ];
    }

    #[DataProvider('excelMonthsProvider')]
    public function test_parses_excel_date_garbage(string $raw, float $expected): void
    {
        $this->assertSame($expected, $this->abv(['abv' => $raw, 'name_full' => '']));
    }

    public function test_parses_plain_number_with_dot(): void
    {
        $this->assertSame(6.0, $this->abv(['abv' => '6', 'name_full' => '']));
        $this->assertSame(0.5, $this->abv(['abv' => '0.5', 'name_full' => '']));
    }

    public function test_parses_plain_number_with_comma(): void
    {
        $this->assertSame(4.5, $this->abv(['abv' => '4,5', 'name_full' => '']));
    }

    public function test_falls_back_to_name_regex(): void
    {
        $this->assertSame(4.2, $this->abv(['abv' => '', 'name_full' => 'Пиво светлое алк. 4,2%']));
    }

    public function test_unparseable_value_warns_and_returns_null(): void
    {
        $ctx = new ImportContext($this->row(['abv' => 'мусор', 'name_full' => '']));
        (new ResolveAbvStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertNull($ctx->attributes['abv']);
        $this->assertCount(1, $ctx->warnings);
        $this->assertSame('unparsed ABV', $ctx->warnings[0]['message']);
    }

    public function test_empty_value_returns_null_without_warning(): void
    {
        $ctx = new ImportContext($this->row(['abv' => '', 'name_full' => '']));
        (new ResolveAbvStage)($ctx, fn (ImportContext $c) => $c);

        $this->assertNull($ctx->attributes['abv']);
        $this->assertSame([], $ctx->warnings);
    }

    public function test_pipeline_continues_to_next(): void
    {
        $ctx = new ImportContext($this->row(['abv' => '5', 'name_full' => '']));
        $result = (new ResolveAbvStage)($ctx, function (ImportContext $c) {
            $c->attributes['next_ran'] = true;

            return $c;
        });

        $this->assertTrue($result->attributes['next_ran']);
    }
}
