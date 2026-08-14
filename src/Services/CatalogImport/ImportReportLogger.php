<?php

namespace Services\CatalogImport;

use Illuminate\Support\Facades\File;
use Services\CatalogImport\Dto\ImportReport;
use Throwable;

/**
 * Пишет текстовый отчёт о запуске `catalog:import` — один файл на каждый запуск,
 * включая dry-run и упавший импорт.
 *
 * Намеренно не Monolog-канал: `Log::build()` префиксует каждую строку
 * "[дата] channel.INFO:", что ломает читаемое выравнивание таблицы метрик.
 *
 * Каталог — `catalog_import.report_log_dir` относительно storage_path().
 */
final class ImportReportLogger
{
    /** Ширина колонки подписи в блоке метрик (символы, не байты). */
    private const LABEL_WIDTH = 25;

    /**
     * Метрики отчёта: подпись => значение.
     *
     * Единый источник и для консольной таблицы (CatalogImportCommand::printReport),
     * и для файла — чтобы они не разъезжались при добавлении новых счётчиков.
     *
     * @return array<string, string|int>
     */
    public static function metrics(ImportReport $report): array
    {
        return [
            'Строк обработано' => $report->processed,
            'Строк пропущено' => $report->skipped,
            'Битых строк CSV' => $report->malformedRows,
            'Производителей создано' => $report->manufacturersCreated,
            'Стилей пива создано' => $report->stylesCreated,
            'Товаров создано' => $report->productsCreated,
            'Товаров обновлено' => $report->productsUpdated,
            'Товаров без изменений' => $report->productsUnchanged,
            'Изображений загружено' => $report->imagesAttached,
            'Предупреждений' => $report->warningsTotal,
            'Расхождений объёма' => count($report->volumeDiscrepancies),
            'Длительность' => self::formatDuration($report->durationMs),
        ];
    }

    /**
     * @param  array<string, string>  $params  Шапка запуска (CatalogImportCommand::buildParams).
     * @return string Путь к записанному файлу.
     */
    public function writeSuccess(array $params, ImportReport $report, bool $dryRun): string
    {
        $status = $dryRun ? 'тестовый прогон (dry-run), изменения откачены' : 'успех';

        $lines = [
            ...$this->headerLines($params, $status),
            '',
            ...$this->metricLines($report),
            '',
            ...$this->warningLines($report),
            '',
            ...$this->volumeDiscrepancyLines($report),
        ];

        return $this->write($lines, $dryRun ? '-dry-run' : '');
    }

    /**
     * @param  array<string, string>  $params
     * @return string Путь к записанному файлу.
     */
    public function writeFailure(array $params, Throwable $e, bool $dryRun): string
    {
        $lines = [
            ...$this->headerLines($params, 'ОШИБКА'),
            '',
            'Исключение: '.$e::class,
            'Сообщение:  '.$e->getMessage(),
            'Место:      '.$e->getFile().':'.$e->getLine(),
            '',
            'Stacktrace:',
            $e->getTraceAsString(),
        ];

        return $this->write($lines, ($dryRun ? '-dry-run' : '').'-failed');
    }

    /**
     * @param  array<string, string>  $params
     * @return list<string>
     */
    private function headerLines(array $params, string $status): array
    {
        $rows = [...$params, 'Статус' => $status];
        $width = max(array_map('mb_strlen', array_keys($rows)));

        $lines = ['=== Импорт каталога '.now()->format('Y-m-d H:i:s').' ==='];

        foreach ($rows as $key => $value) {
            $lines[] = $key.':'.str_repeat(' ', $width - mb_strlen((string) $key) + 1).$value;
        }

        return $lines;
    }

    /** @return list<string> */
    private function metricLines(ImportReport $report): array
    {
        $lines = [];

        foreach (self::metrics($report) as $label => $value) {
            // str_pad считает байты — на кириллице разъезжается; выравниваем по mb_strlen.
            $dots = str_repeat('.', max(3, self::LABEL_WIDTH - mb_strlen($label)));
            $lines[] = "{$label} {$dots} {$value}";
        }

        return $lines;
    }

    /** @return list<string> */
    private function warningLines(ImportReport $report): array
    {
        $lines = ["--- Предупреждения ({$report->warningsTotal}) ---"];

        foreach ($report->warnings as $w) {
            $lines[] = "строка {$w['line']} [{$w['stage']}] {$w['message']}: {$w['value']}";
        }

        $hidden = $report->warningsTotal - count($report->warnings);

        if ($hidden > 0) {
            $lines[] = "... ещё {$hidden} не сохранено (лимит catalog_import.warning_limit)";
        }

        return $lines;
    }

    /** @return list<string> */
    private function volumeDiscrepancyLines(ImportReport $report): array
    {
        $lines = ['--- Расхождения объёма ('.count($report->volumeDiscrepancies).') ---'];

        foreach ($report->volumeDiscrepancies as $d) {
            $lines[] = "строка {$d['line']} код={$d['external_code']} Упаковка={$d['package_ml']} мл, {$d['source']}={$d['text_ml']} мл";
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return string Путь к записанному файлу.
     */
    private function write(array $lines, string $suffix): string
    {
        $dir = storage_path(config('catalog_import.report_log_dir', 'logs/catalog-import/'));

        File::ensureDirectoryExists($dir);

        $path = $this->uniquePath($dir, now()->format('Y-m-d_His').$suffix);

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);

        // storage_path() отдаёт разделитель ОС, а report_log_dir — прямые слэши;
        // без нормализации путь в консоли получается смешанным (…\logs/catalog-import\…).
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Два запуска в одну секунду (например, два быстро упавших) не должны затирать друг друга.
     */
    private function uniquePath(string $dir, string $base): string
    {
        $dir = rtrim($dir, '/\\').DIRECTORY_SEPARATOR;
        $path = $dir.$base.'.log';

        for ($i = 2; file_exists($path); $i++) {
            $path = $dir.$base.'_'.$i.'.log';
        }

        return $path;
    }

    private static function formatDuration(int $ms): string
    {
        if ($ms < 1000) {
            return "{$ms} мс";
        }

        $seconds = $ms / 1000;

        if ($seconds < 60) {
            return number_format($seconds, 1, '.', '').' с';
        }

        $minutes = intdiv((int) $seconds, 60);

        return $minutes.' мин '.number_format($seconds - $minutes * 60, 1, '.', '').' с';
    }
}
