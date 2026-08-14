<?php

namespace App\Console\Commands;

use Domain\Catalog\Models\Category;
use Illuminate\Console\Command;
use Infrastructure\Ftp\Catalog1cFtpClient;
use Infrastructure\Jobs\ZeroOutStaleProductsJob;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\CsvParserService;
use Services\CatalogImport\Dto\ImportOptions;
use Services\CatalogImport\Dto\ImportReport;
use Services\CatalogImport\ImportReportLogger;
use Throwable;

class CatalogImportCommand extends Command
{
    protected $signature = 'catalog:import
        {path? : Явный путь к локальному CSV файлу (пропускает загрузку с FTP)}
        {--dry-run : Запустить весь пайплайн в транзакции и откатить — данные не записываются}
        {--no-download : Пропустить загрузку с FTP и использовать последний скачанный файл из storage}
        {--categories= : Импортировать только указанные slug категорий через запятую (напр. beer,mead)}';

    protected $description = 'Импорт товаров в каталог из CSV-выгрузки 1С';

    /** Откуда взят CSV — только для шапки файла отчёта. Заполняется resolveImportPath(). */
    private string $sourceLabel = 'неизвестен';

    public function handle(CsvParserService $service, Catalog1cFtpClient $ftp, ImportReportLogger $logger, CatalogImportSettings $catalogImportSettings): int
    {
        // Реестр категорий редактируется из MoonShine — префлайт ловит
        // нарушения инвариантов (дубли alcohol/when, пустые value и т.п.),
        // которые раньше были невозможны при единственном источнике —
        // config/catalog_import.php. См. ValidateCategoryRegistryCommand.
        if ($this->call('catalog:validate-registry') !== self::SUCCESS) {
            $this->error('Импорт отменён: реестр категорий не прошёл проверку (см. вывод catalog:validate-registry выше).');

            return self::FAILURE;
        }

        $path = $this->resolveImportPath($ftp);

        if ($path === null) {
            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
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

        $params = $this->buildParams($path, $isDryRun, $categoryFilter);

        try {
            $report = $service->import($path, new ImportOptions(dryRun: $isDryRun, categoryFilter: $categoryFilter));
        } catch (Throwable $e) {
            $this->error("Импорт не выполнен: {$e->getMessage()}");
            $this->writeReportLog(fn () => $logger->writeFailure($params, $e, $isDryRun));

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Транзакция откачена.');
        }

        $this->printReport($report);

        // seenCodesTracked уже кодирует "не dry-run и без --categories" —
        // повторно эти условия здесь не проверяем, см. CsvParserService::import().
        if ($report->seenCodesTracked && $catalogImportSettings->zero_out_missing) {
            ZeroOutStaleProductsJob::dispatch();
            $this->info('Задача обнуления пропавших товаров поставлена в очередь.');
        }

        $this->writeReportLog(fn () => $logger->writeSuccess($params, $report, $isDryRun));

        return self::SUCCESS;
    }

    /**
     * Шапка файла отчёта: с какими входными данными запускался импорт.
     *
     * @param  list<string>  $categoryFilter
     * @return array<string, string>
     */
    private function buildParams(string $path, bool $isDryRun, array $categoryFilter): array
    {
        $transactionMode = (string) config('catalog_import.transaction_mode', 'row');
        $chunkSize = (int) config('catalog_import.chunk_size', 500);
        $zeroOutMissing = app(CatalogImportSettings::class)->zero_out_missing;

        return [
            // storage_path() отдаёт разделитель ОС, а download_dir — прямые слэши.
            'Файл' => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            'Источник' => $this->sourceLabel,
            'Режим' => $isDryRun ? 'тестовый (--dry-run)' : 'обычный',
            'Категории' => $categoryFilter === [] ? 'все' : implode(', ', $categoryFilter),
            'Транзакции' => $transactionMode === 'chunk'
                ? "chunk (chunk_size {$chunkSize})"
                : $transactionMode,
            'Обнуление пропавших товаров' => match (true) {
                $isDryRun, $categoryFilter !== [] => 'не применяется (dry-run или фильтр категорий)',
                $zeroOutMissing => 'включено',
                default => 'выключено в настройках',
            },
        ];
    }

    /**
     * Недоступный storage/logs не должен превращать успешный импорт в FAILURE —
     * ошибка записи отчёта только предупреждает.
     *
     * @param  callable(): string  $write
     */
    private function writeReportLog(callable $write): void
    {
        try {
            $this->info('Отчёт: '.$write());
        } catch (Throwable $e) {
            $this->warn("Не удалось записать файл отчёта: {$e->getMessage()}");
        }
    }

    private function printReport(ImportReport $report): void
    {
        $rows = [];

        foreach (ImportReportLogger::metrics($report) as $label => $value) {
            $rows[] = [$label, $value];
        }

        $this->table(['Метрика', 'Количество'], $rows);

        if ($report->warningsTotal > 0) {
            $this->newLine();
            $this->warn('Предупреждения:');
            foreach (array_slice($report->warnings, 0, 50) as $w) {
                $this->line("  строка {$w['line']} [{$w['stage']}] {$w['message']}: {$w['value']}");
            }
            if ($report->warningsTotal > 50) {
                $this->line('  ... и ещё '.($report->warningsTotal - 50).' (полный список — в файле отчёта)');
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
     * Побочный эффект: заполняет $this->sourceLabel для шапки файла отчёта.
     *
     * @return string|null Локальный путь, или null если загрузка не удалась.
     */
    private function resolveImportPath(Catalog1cFtpClient $ftp): ?string
    {
        if ($this->argument('path') !== null) {
            $this->sourceLabel = 'локальный файл (аргумент path)';

            return $this->argument('path');
        }

        $file = config('catalog_import.base_ftp_file');

        if ($file === null) {
            $this->error('Ключ конфига не найден: catalog_import.base_ftp_file');

            return null;
        }

        if ($this->option('no-download')) {
            $this->sourceLabel = 'последний скачанный файл (--no-download)';
            $path = config('catalog_import.download_dir').$file;

            return storage_path($path);
        }

        $host = config('services.catalog_1c_ftp.host');
        $port = config('services.catalog_1c_ftp.port');
        $this->sourceLabel = "FTP {$host}:{$port}/{$file}";
        $this->info("Загрузка с FTP: {$host}:{$port}/{$file}");

        try {
            return $ftp->download($file, storage_path(config('catalog_import.download_dir').basename($file)));
        } catch (Throwable $e) {
            $this->error("Загрузка с FTP не удалась: {$e->getMessage()}");

            return null;
        }
    }
}
