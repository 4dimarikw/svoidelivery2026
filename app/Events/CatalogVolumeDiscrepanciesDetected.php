<?php

namespace App\Events;

/**
 * Расхождения объёма, найденные DetectVolumeMismatchStage за прогон
 * catalog:import: Упаковка (авторитетный источник) не совпадает с объёмом,
 * распознанным в Наименование/Артикул. Попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * CatalogImportCompleted/Failed, CatalogStaleProductsZeroed).
 */
final readonly class CatalogVolumeDiscrepanciesDetected implements LoggableEvent
{
    /**
     * @param  list<array{line: int, external_code: string, source: string, package_raw: string, package_ml: int, text_raw: string, text_ml: int}>  $discrepancies
     */
    public function __construct(
        public string $path,
        public array $discrepancies,
    ) {}

    public function eventType(): string
    {
        return 'catalog_import.volume_discrepancies_detected';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function message(): string
    {
        return sprintf(
            'Обнаружено %d расхождений объёма между Упаковкой и Наименованием/Артикулом.',
            count($this->discrepancies),
        );
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'count' => count($this->discrepancies),
            'discrepancies' => $this->discrepancies,
        ];
    }
}
