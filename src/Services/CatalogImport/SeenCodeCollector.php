<?php

namespace Services\CatalogImport;

use Illuminate\Support\Facades\DB;
use Services\CatalogImport\Dto\RawRow;

/**
 * Пишет в catalog_import_seen_codes коды товаров (products.external_code),
 * реально встреченные в текущем CSV — рабочая область для
 * Domain\Catalog\Actions\ZeroOutStaleProductsAction, которое после успешного
 * импорта обнуляет остаток у всего, чего здесь нет.
 *
 * Буферизует вставки (insertOrIgnore пачками), а не пишет построчно — импорт
 * гоняет тысячи строк за прогон. add() принимает RawRow ДО пайплайна
 * стейджей, поэтому отбракованные строки (NormalizeRowStage и т.п.) тоже
 * считаются "виденными" — присутствие в файле, не факт успешного импорта,
 * см. докблок ZeroOutStaleProductsAction.
 */
final class SeenCodeCollector
{
    private const TABLE = 'catalog_import_seen_codes';

    private const FLUSH_THRESHOLD = 1000;

    /** @var list<string> */
    private array $buffer = [];

    public function reset(): void
    {
        $this->buffer = [];

        DB::table(self::TABLE)->truncate();
    }

    public function add(RawRow $row): void
    {
        $code = $row->get(config('catalog_import.columns.product_code'));

        if ($code === '') {
            return;
        }

        $this->buffer[] = $code;

        if (count($this->buffer) >= self::FLUSH_THRESHOLD) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        // insertOrIgnore, не insert — один КодТовара может повторяться в CSV
        // (несколько строк одного товара), а PK на external_code это отловит.
        DB::table(self::TABLE)->insertOrIgnore(
            array_map(static fn (string $code): array => ['external_code' => $code], array_unique($this->buffer))
        );

        $this->buffer = [];
    }
}
