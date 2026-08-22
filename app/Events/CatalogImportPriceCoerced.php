<?php

namespace App\Events;

/**
 * Итог прогона catalog:import: сколько раз Services\CatalogImport\Stages\ResolvePriceStage
 * не смогла распарсить Цену/Остаток и молча привела значение к 0 — попадает в
 * event_logs через App\Listeners\PersistEventLog (автодискавери по LoggableEvent).
 * Денежный эффект прямой: товар получает нулевую цену и остаётся в каталоге.
 * Диспатчится из Services\CatalogImport\CsvParserService::import() рядом с
 * CatalogImportCompleted, только когда есть хотя бы одно приведение.
 */
final readonly class CatalogImportPriceCoerced implements LoggableEvent
{
    /** @param  list<array{line: int, stage: string, message: string, value: string}>  $examples */
    public function __construct(
        public string $path,
        public int $coercedPriceCount,
        public int $coercedStockCount,
        public array $examples,
    ) {}

    public function eventType(): string
    {
        return 'catalog_import.price_coerced';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function message(): string
    {
        return sprintf(
            'Импорт привёл к 0 нечисловые значения: цена — %d раз(а), остаток — %d раз(а).',
            $this->coercedPriceCount,
            $this->coercedStockCount,
        );
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'coerced_price_count' => $this->coercedPriceCount,
            'coerced_stock_count' => $this->coercedStockCount,
            'examples' => $this->examples,
        ];
    }
}
