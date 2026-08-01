<?php

namespace Services\CatalogImport\Dto;

class ImportReport
{
    public int $processed = 0;

    public int $skipped = 0;

    public int $malformedRows = 0;

    public int $brandsCreated = 0;

    public int $stylesCreated = 0;

    public int $productsCreated = 0;

    public int $productsUpdated = 0;

    public int $productsUnchanged = 0;

    public int $variationsCreated = 0;

    public int $variationsUpdated = 0;

    public int $variationsUnchanged = 0;

    public int $barcodesCreated = 0;

    public int $imagesAttached = 0;

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
