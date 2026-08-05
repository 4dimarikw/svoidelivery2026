<?php

namespace Tests\Unit\CatalogImport;

use RuntimeException;
use Services\CatalogImport\CsvReader;
use Tests\TestCase;

class CsvReaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir().'/csv-reader-test-'.uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir.'/*'));
        rmdir($this->tmpDir);

        parent::tearDown();
    }

    /**
     * Пишет CSV в Windows-1251 (как реальная выгрузка 1С) и возвращает путь.
     */
    private function writeCsv(string $utf8Content, string $encoding = 'Windows-1251', bool $withBom = false): string
    {
        $path = $this->tmpDir.'/'.uniqid().'.csv';
        $converted = mb_convert_encoding($utf8Content, $encoding, 'UTF-8');

        file_put_contents($path, ($withBom ? "\xEF\xBB\xBF" : '').$converted);

        return $path;
    }

    public function test_reads_and_converts_windows_1251_to_utf8(): void
    {
        $path = $this->writeCsv("Категория;Цена\nПиво;100\n");

        $rows = iterator_to_array((new CsvReader)->read($path));

        $this->assertCount(1, $rows);
        $this->assertSame('Пиво', $rows[0]->get('Категория'));
        $this->assertSame('100', $rows[0]->get('Цена'));
    }

    public function test_strips_bom_from_first_header(): void
    {
        // BOM осмыслен только для UTF-8-источника (Windows-1251 в реальной
        // выгрузке 1С BOM никогда не несёт) — encoding: 'UTF-8' отключает
        // mb_convert_encoding в CsvReader, иначе BOM-байты сами превратились
        // бы в мусор при перекодировке из несуществующего "Windows-1251 BOM".
        $path = $this->writeCsv("Категория;Цена\nПиво;100\n", encoding: 'UTF-8', withBom: true);

        $rows = iterator_to_array((new CsvReader)->read($path, encoding: 'UTF-8'));

        // Без снятия BOM первый заголовок был бы "\xEF\xBB\xBFКатегория",
        // и get('Категория') не нашёл бы колонку.
        $this->assertSame('Пиво', $rows[0]->get('Категория'));
    }

    public function test_custom_delimiter(): void
    {
        $path = $this->writeCsv("Категория,Цена\nПиво,100\n");

        $rows = iterator_to_array((new CsvReader)->read($path, delimiter: ','));

        $this->assertSame('Пиво', $rows[0]->get('Категория'));
    }

    public function test_malformed_row_triggers_callback_and_is_skipped(): void
    {
        // Вторая строка данных содержит на одну колонку меньше заголовка.
        $path = $this->writeCsv("Категория;Цена;Марка\nПиво;100;X\nТолькоКатегория\nВино;200;Y\n");

        $malformed = 0;
        $rows = iterator_to_array((new CsvReader)->read($path, onMalformedRow: function () use (&$malformed) {
            $malformed++;
        }));

        $this->assertSame(1, $malformed);
        $this->assertCount(2, $rows);
        $this->assertSame('Пиво', $rows[0]->get('Категория'));
        $this->assertSame('Вино', $rows[1]->get('Категория'));
    }

    public function test_line_numbers_count_data_rows_from_the_header(): void
    {
        $path = $this->writeCsv("Категория;Цена\nПиво;100\nВино;200\n");

        $rows = iterator_to_array((new CsvReader)->read($path));

        // Строка 1 — заголовок, поэтому первая строка данных — строка 2.
        $this->assertSame(2, $rows[0]->lineNumber);
        $this->assertSame(3, $rows[1]->lineNumber);
    }

    public function test_missing_file_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array((new CsvReader)->read($this->tmpDir.'/does-not-exist.csv'));
    }

    public function test_stream_wrapper_path_is_rejected(): void
    {
        // realpath() отклоняет php://, phar://, http:// — они не файлы на диске.
        $this->expectException(RuntimeException::class);

        iterator_to_array((new CsvReader)->read('php://memory'));
    }

    public function test_utf8_source_is_not_reconverted(): void
    {
        $path = $this->writeCsv("Категория;Цена\nПиво;100\n", encoding: 'UTF-8');

        $rows = iterator_to_array((new CsvReader)->read($path, encoding: 'UTF-8'));

        $this->assertSame('Пиво', $rows[0]->get('Категория'));
    }
}
