<?php

namespace App\Events;

/**
 * Итог прогона catalog:import: сколько строк CSV не превратились в товар —
 * попадает в event_logs через App\Listeners\PersistEventLog (автодискавери по
 * LoggableEvent, как и CatalogImportCompleted). Диспатчится из
 * Services\CatalogImport\CsvParserService::import() рядом с CatalogImportCompleted,
 * только когда $report->skipped > 0. Одно событие на прогон, а не на строку —
 * иначе построчный цикл импорта завалил бы event_logs тысячами записей.
 */
final readonly class CatalogImportRowsSkipped implements LoggableEvent
{
    /**
     * @param  array<string, int>  $byStage  Класс стадии, отдавшей skip → количество строк.
     * @param  list<array{line: int, stage: string, message: string, value: string}>  $examples
     */
    public function __construct(
        public string $path,
        public int $skippedTotal,
        public int $malformedRows,
        public array $byStage,
        public array $examples,
    ) {}

    public function eventType(): string
    {
        return 'catalog_import.rows_skipped';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function message(): string
    {
        return sprintf(
            'Импорт пропустил %d строк (%d с неверным числом колонок).',
            $this->skippedTotal,
            $this->malformedRows,
        );
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'skipped_total' => $this->skippedTotal,
            'malformed_rows' => $this->malformedRows,
            'by_stage' => $this->byStage,
            'examples' => $this->examples,
        ];
    }
}
