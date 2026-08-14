<?php

namespace App\Events;

use Domain\Catalog\Actions\ZeroOutStaleProductsResult;

/**
 * Итог ZeroOutStaleProductsJob — попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * CatalogImportCompleted/Failed).
 */
final readonly class CatalogStaleProductsZeroed implements LoggableEvent
{
    public function __construct(
        public ZeroOutStaleProductsResult $result,
    ) {}

    public function eventType(): string
    {
        return 'catalog_import.stale_products_zeroed';
    }

    public function level(): string
    {
        return $this->result->aborted ? 'warning' : 'info';
    }

    public function message(): string
    {
        if ($this->result->reason === 'no_seen_codes') {
            return 'Обнуление пропавших товаров пропущено: список кодов из CSV пуст.';
        }

        if ($this->result->reason === 'threshold_exceeded') {
            return sprintf(
                'Обнуление пропавших товаров отменено: превышен порог (%d из %d в наличии, лимит %d%%).',
                $this->result->staleFound,
                $this->result->inStockTotal,
                $this->result->maxPercent,
            );
        }

        return "Обнулено {$this->result->zeroed} товаров, отсутствующих в последней выгрузке 1С.";
    }

    public function context(): array
    {
        return [
            'zeroed' => $this->result->zeroed,
            'stale_found' => $this->result->staleFound,
            'in_stock_total' => $this->result->inStockTotal,
            'seen_total' => $this->result->seenTotal,
            'max_percent' => $this->result->maxPercent,
            'aborted' => $this->result->aborted,
            'reason' => $this->result->reason,
        ];
    }
}
