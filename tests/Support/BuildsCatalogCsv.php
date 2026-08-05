<?php

namespace Tests\Support;

/**
 * Строит временный CSV-файл в Windows-1251 (как реальная выгрузка 1С) из
 * строк, заданных семантическими ключами (config('catalog_import.columns')),
 * для сквозных тестов CsvParserService/catalog:import.
 */
trait BuildsCatalogCsv
{
    /** @var list<string> Файлы, которые нужно удалить в tearDown(). */
    private array $catalogCsvTmpFiles = [];

    /**
     * @param  list<array<string, string>>  $rows  Список строк, каждая — семантический_ключ => значение.
     * @param  list<string>|null  $columnOrder  Порядок семантических ключей в заголовке; по умолчанию — все ключи из config('catalog_import.columns').
     */
    private function writeCatalogCsv(array $rows, ?array $columnOrder = null): string
    {
        $columns = config('catalog_import.columns');
        $columnOrder ??= array_keys($columns);

        $lines = [implode(';', array_map(fn (string $key) => $columns[$key], $columnOrder))];

        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(fn (string $key) => $row[$key] ?? '', $columnOrder));
        }

        $path = sys_get_temp_dir().'/catalog-import-test-'.uniqid().'.csv';
        $content = mb_convert_encoding(implode("\n", $lines)."\n", 'Windows-1251', 'UTF-8');
        file_put_contents($path, $content);

        $this->catalogCsvTmpFiles[] = $path;

        return $path;
    }

    /**
     * Пишет сырой (уже готовый) текст файла — для тестов "битых" строк, где
     * нужен явный контроль над числом колонок по строкам.
     */
    private function writeRawCatalogCsv(string $utf8Content): string
    {
        $path = sys_get_temp_dir().'/catalog-import-test-'.uniqid().'.csv';
        file_put_contents($path, mb_convert_encoding($utf8Content, 'Windows-1251', 'UTF-8'));

        $this->catalogCsvTmpFiles[] = $path;

        return $path;
    }

    private function cleanupCatalogCsvTmpFiles(): void
    {
        foreach ($this->catalogCsvTmpFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->catalogCsvTmpFiles = [];
    }

    /**
     * Базовая валидная строка алкогольной категории — резолвится в 'beer'
     * (единственное активное alcohol/default правило в мигрированном реестре).
     *
     * @return array<string, string>
     */
    private function validCatalogRow(array $overrides = []): array
    {
        return array_merge([
            'object_id' => '1',
            'product_code' => 'CODE-'.uniqid(),
            'article' => 'ART-1',
            'price' => '199.90',
            'category' => 'Алкогольная продукция',
            'manufacturer' => 'Тестовый Пивзавод',
            'abv' => '5',
            'beer_style' => '',
            'name_full' => 'Пиво светлое тестовое',
            'untappd_ref' => '',
            'package' => 'кор. 12х0,5л ж/б',
            'brand' => 'Тестовая Марка',
            'product' => '',
            'ibu' => '20',
            'plato' => '12',
            'ebc' => '8',
            'shelf_life' => '180',
            'stock' => '50',
            'sales_rating' => '',
            'description' => 'Тестовое описание',
        ], $overrides);
    }
}
