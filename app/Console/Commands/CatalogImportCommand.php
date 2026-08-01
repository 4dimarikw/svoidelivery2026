<?php

namespace App\Console\Commands;

use Domain\Catalog\Models\Category;
use Illuminate\Console\Command;
use Infrastructure\Ftp\Catalog1cFtpClient;
use Services\CatalogImport\CsvParserService;
use Services\CatalogImport\Dto\ImportOptions;
use Services\CatalogImport\Dto\ImportReport;
use Throwable;

class CatalogImportCommand extends Command
{
    protected $signature = 'catalog:import
        {path? : Явный путь к локальному CSV файлу (пропускает загрузку с FTP)}
        {--dry-run : Запустить весь пайплайн в транзакции и откатить — данные не записываются}
        {--no-download : Пропустить загрузку с FTP и использовать последний скачанный файл из storage}
        {--categories= : Импортировать только указанные slug категорий через запятую (напр. beer,mead)}';

    protected $description = 'Импорт товаров в каталог из CSV-выгрузки 1С';

    public function handle(CsvParserService $service, Catalog1cFtpClient $ftp): int
    {
        $path = $this->resolveImportPath($ftp);

        if ($path === null) {
            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $categoryFilter = $this->parseCategoryFilter();

        if ($categoryFilter === null) {
            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Изменения не будут сохранены.');
        }

        if ($categoryFilter !== []) {
            $this->info('Фильтр категорий: '.implode(', ', $categoryFilter));
        }

        $this->info("Импорт: {$path}");

        try {
            $report = $service->import($path, new ImportOptions(dryRun: $isDryRun, categoryFilter: $categoryFilter));
        } catch (Throwable $e) {
            $this->error("Импорт не выполнен: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Транзакция откачена.');
        }

        $this->printReport($report);

        return self::SUCCESS;
    }

    private function printReport(ImportReport $report): void
    {
        $rows = [
            ['Строк обработано', $report->processed],
            ['Строк пропущено', $report->skipped],
            ['Битых строк CSV', $report->malformedRows],
            ['Производителей создано', $report->manufacturersCreated],
            ['Стилей пива создано', $report->stylesCreated],
            ['Товаров создано', $report->productsCreated],
            ['Товаров обновлено', $report->productsUpdated],
            ['Товаров без изменений', $report->productsUnchanged],
            ['Штрихкодов создано', $report->barcodesCreated],
            ['Изображений загружено', $report->imagesAttached],
            ['Предупреждений', $report->warningsTotal],
        ];

        $this->table(['Метрика', 'Количество'], $rows);

        if ($report->warningsTotal > 0) {
            $this->newLine();
            $this->warn('Предупреждения:');
            foreach (array_slice($report->warnings, 0, 50) as $w) {
                $this->line("  строка {$w['line']} [{$w['stage']}] {$w['message']}: {$w['value']}");
            }
            if ($report->warningsTotal > 50) {
                $this->line('  ... и ещё '.($report->warningsTotal - 50));
            }
        }
    }

    /**
     * Парсит --categories в массив валидных slug.
     *
     * Возвращает:
     * - пустой массив — флаг не передан, фильтр выключен;
     * - массив slug — только реально существующие slug из БД (неизвестные отброшены с warn);
     * - null — все переданные slug неизвестны; команда должна завершиться с FAILURE.
     *
     * @return list<string>|null
     */
    private function parseCategoryFilter(): ?array
    {
        $raw = $this->option('categories');

        if ($raw === null || $raw === '') {
            return [];
        }

        $slugs = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($slugs === []) {
            return [];
        }

        $known = Category::query()->whereIn('slug', $slugs)->pluck('slug')->all();
        $unknown = array_values(array_diff($slugs, $known));

        if ($unknown !== []) {
            $this->warn('Неизвестные slug категорий (пропущены): '.implode(', ', $unknown));
        }

        if ($known === []) {
            $this->error('Ни один из переданных slug категорий не найден. Импорт отменён.');

            return null;
        }

        return $known;
    }

    /**
     * Определяет путь к CSV файлу для импорта.
     *
     * Приоритет:
     * 1. Явный аргумент `path` — использовать как есть, без загрузки.
     * 2. Флаг `--no-download` — использовать последний скачанный файл из storage.
     * 3. По умолчанию — загрузить с FTP.
     *
     * @return string|null Локальный путь, или null если загрузка не удалась.
     */
    private function resolveImportPath(Catalog1cFtpClient $ftp): ?string
    {
        if ($this->argument('path') !== null) {
            return $this->argument('path');
        }

        $file = config('catalog_import.base_ftp_file');

        if ($file === null) {
            $this->error('Ключ конфига не найден: catalog_import.base_ftp_file');

            return null;
        }

        if ($this->option('no-download')) {
            $path = config('catalog_import.download_dir').$file;

            return storage_path($path);
        }

        $host = config('services.catalog_1c_ftp.host');
        $port = config('services.catalog_1c_ftp.port');
        $this->info("Загрузка с FTP: {$host}:{$port}/{$file}");

        try {
            return $ftp->download($file, storage_path(config('catalog_import.download_dir').basename($file)));
        } catch (Throwable $e) {
            $this->error("Загрузка с FTP не удалась: {$e->getMessage()}");

            return null;
        }
    }
}
