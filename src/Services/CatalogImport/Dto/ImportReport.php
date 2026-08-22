<?php

namespace Services\CatalogImport\Dto;

class ImportReport
{
    public int $processed = 0;

    public int $skipped = 0;

    public int $malformedRows = 0;

    public int $manufacturersCreated = 0;

    public int $stylesCreated = 0;

    public int $productsCreated = 0;

    public int $productsUpdated = 0;

    public int $productsUnchanged = 0;

    public int $imagesAttached = 0;

    public int $priceCoerced = 0;

    public int $stockCoerced = 0;

    /**
     * Класс стадии, отдавшей skip → количество строк — см. ImportContext::$skipStage.
     * Строки без известной стадии (FilterCategoryStage — намеренная фильтрация
     * по --categories) бакетируются под 'filtered'.
     *
     * @var array<string, int>
     */
    public array $skippedByStage = [];

    /**
     * true, если CsvParserService вёл catalog_import_seen_codes для этого
     * прогона (не dry-run и без --categories) — CatalogImportCommand решает
     * по этому флагу, можно ли ставить ZeroOutStaleProductsJob в очередь.
     * См. Services\CatalogImport\SeenCodeCollector.
     */
    public bool $seenCodesTracked = false;

    /** Длительность импорта, мс. Заполняется CsvParserService, в т.ч. в dry-run. */
    public int $durationMs = 0;

    /** Истинный счётчик всех предупреждений, не ограниченный warningLimit. */
    public int $warningsTotal = 0;

    /** @var list<array{line: int, stage: string, message: string, value: string}> */
    public array $warnings = [];

    /**
     * Расхождения объёма между Упаковкой (авторитетный источник) и
     * Наименованием/Артикулом — см. DetectVolumeMismatchStage. Штучные
     * находки, не тысячи, как $warnings — отдельный limit-механизм не нужен.
     *
     * @var list<array{line: int, external_code: string, source: string, package_raw: string, package_ml: int, text_raw: string, text_ml: int}>
     */
    public array $volumeDiscrepancies = [];

    /**
     * @param  list<array{stage: string, message: string, value: string}>  $contextWarnings
     * @param  int|null  $limit  Если задан — хранить не более N записей в $warnings; warningsTotal растёт всегда.
     */
    public function addWarnings(int $line, array $contextWarnings, ?int $limit = null): void
    {
        foreach ($contextWarnings as $w) {
            $this->warningsTotal++;
            if ($limit === null || count($this->warnings) < $limit) {
                $this->warnings[] = ['line' => $line, ...$w];
            }
        }
    }

    /**
     * @param  list<array{external_code: string, source: string, package_raw: string, package_ml: int, text_raw: string, text_ml: int}>  $contextDiscrepancies
     */
    public function addVolumeDiscrepancies(int $line, array $contextDiscrepancies): void
    {
        foreach ($contextDiscrepancies as $d) {
            $this->volumeDiscrepancies[] = ['line' => $line, ...$d];
        }
    }
}
