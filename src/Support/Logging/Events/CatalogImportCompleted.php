<?php

namespace Support\Logging\Events;

final readonly class CatalogImportCompleted implements LoggableEvent
{
    /**
     * @param list<array{line: int, stage: string, message: string, value: string}> $warnings
     */
    public function __construct(
        public string $path,
        public int    $processed,
        public int    $skipped,
        public int    $brandsCreated,
        public int    $stylesCreated,
        public int    $productsCreated,
        public int    $productsUpdated,
        public int    $variationsCreated,
        public int    $variationsUpdated,
        public int    $barcodesCreated,
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
            'brands_created' => $this->brandsCreated,
            'styles_created' => $this->stylesCreated,
            'products_created' => $this->productsCreated,
            'products_updated' => $this->productsUpdated,
            'variations_created' => $this->variationsCreated,
            'variations_updated' => $this->variationsUpdated,
            'barcodes_created' => $this->barcodesCreated,
            'warnings_count' => $this->warningsCount,
            'warnings' => $this->warnings,
            'duration_ms' => $this->durationMs,
        ];
    }
}
