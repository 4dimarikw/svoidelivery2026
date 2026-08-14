<?php

namespace Domain\Catalog\Actions;

/**
 * Итог одного прогона ZeroOutStaleProductsAction — несёт достаточно данных,
 * чтобы CatalogStaleProductsZeroed::message()/context() не заглядывали
 * обратно в БД.
 */
final readonly class ZeroOutStaleProductsResult
{
    public function __construct(
        public int $zeroed,
        public int $staleFound,
        public int $inStockTotal,
        public int $seenTotal,
        public int $maxPercent,
        public bool $aborted,
        public ?string $reason = null,
    ) {}
}
