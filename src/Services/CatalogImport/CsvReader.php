<?php

namespace Services\CatalogImport;

use Closure;
use Generator;
use RuntimeException;
use Services\CatalogImport\Dto\RawRow;

class CsvReader
{
    /**
     * @param  Closure|null  $onMalformedRow  Вызывается для каждой строки с неверным числом колонок.
     * @return Generator<int, RawRow>
     */
    public function read(string $path, string $encoding = 'Windows-1251', string $delimiter = ';', ?Closure $onMalformedRow = null): Generator
    {
        // realpath rejects stream wrappers (php://, phar://, http://) — they return false.
        // Caller is responsible for directory whitelisting if this is ever exposed via HTTP upload.
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new RuntimeException("CSV file not found: {$path}");
        }

        $handle = fopen($realPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open: {$realPath}");
        }

        $needsConversion = strtoupper($encoding) !== 'UTF-8';
        $headers = null;
        $line = 0;

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;

                if ($needsConversion) {
                    $row = array_map(
                        fn (string $cell): string => mb_convert_encoding($cell, 'UTF-8', $encoding),
                        $row
                    );
                }

                if ($headers === null) {
                    // Strip UTF-8 BOM from first header if present
                    $first = $row[0] ?? '';
                    if (str_starts_with($first, "\xEF\xBB\xBF")) {
                        $row[0] = substr($first, 3);
                    }
                    $headers = $row;

                    continue;
                }

                if (count($row) !== count($headers)) {
                    if ($onMalformedRow !== null) {
                        $onMalformedRow();
                    }

                    continue;
                }

                yield new RawRow(array_combine($headers, $row), $line);
            }
        } finally {
            fclose($handle);
        }
    }
}
