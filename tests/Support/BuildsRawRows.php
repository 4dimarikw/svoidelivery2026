<?php

namespace Tests\Support;

use Services\CatalogImport\Dto\RawRow;

/**
 * Строит RawRow из семантических ключей (совпадающих с config('catalog_import.columns'))
 * вместо русских заголовков CSV — так тест стейджа не завязан на текст заголовка.
 * Вынесено из tests/Unit/CategorySlugResolverTest.php, где жил оригинал.
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
