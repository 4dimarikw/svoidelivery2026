<?php

namespace Tests\Support;

use Services\CatalogImport\Dto\RawRow;

/**
 * Создаёт RawRow из семантических ключей catalog_import.columns, чтобы тесты этапов не зависели от русских
 * заголовков CSV.
 */
trait BuildsRawRows
{
    /**
     * @param  array<string, string>  $data  Семантический_ключ => значение.
     */
    private function row(array $data, int $lineNumber = 1): RawRow
    {
        $columns = config('catalog_import.columns');

        $mapped = [];
        foreach ($data as $key => $value) {
            $mapped[$columns[$key]] = $value;
        }

        return new RawRow($mapped, $lineNumber);
    }
}
