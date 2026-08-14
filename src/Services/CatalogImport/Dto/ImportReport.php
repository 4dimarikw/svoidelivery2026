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
}
