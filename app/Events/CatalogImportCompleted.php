<?php

namespace App\Events;

final readonly class CatalogImportCompleted implements LoggableEvent
{
    /**
     * @param list<array{line: int, stage: string, message: string, value: string}> $warnings
     */
    public function __construct(
        public string $path,
        public int    $processed,
        public int    $skipped,
        public int    $manufacturersCreated,
        public int    $stylesCreated,
        public int    $productsCreated,
        public int    $productsUpdated,
        public int    $warningsCount,
        public array  $warnings,
        public int    $durationMs,
    )
    {
    }

    public function eventType(): string
    {
        return 'catalog_import.completed';
    }

    public function level(): string
    {
        return 'info';
    }

    public function message(): string
    {
        return "Импорт завершён: обработано {$this->processed} строк, пропущено {$this->skipped}";
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'manufacturers_created' => $this->manufacturersCreated,
            'styles_created' => $this->stylesCreated,
            'products_created' => $this->productsCreated,
            'products_updated' => $this->productsUpdated,
            'warnings_count' => $this->warningsCount,
            'warnings' => $this->warnings,
            'duration_ms' => $this->durationMs,
        ];
    }
}
