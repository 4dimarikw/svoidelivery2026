<?php

namespace Infrastructure\Jobs;

use App\Events\CatalogStaleProductsZeroed;
use Domain\Catalog\Actions\ZeroOutStaleProductsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Обнуляет stock_quantity/in_stock у товаров, отсутствующих в последнем
 * catalog:import — диспатчится из CatalogImportCommand после успешного
 * (не dry-run, без --categories) прогона. Payload пустой: список кодов
 * живёт в catalog_import_seen_codes, не в очереди (десятки тысяч позиций
 * не влезли бы в job).
 */
class ZeroOutStaleProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 1, не 5 — повтор после частичного обнуления пересчитал бы порог
    // (zero_out_max_percent) по уже изменённым данным. Безопаснее один
    // проход; следующий catalog:import всё равно пересоберёт seen_codes.
    public int $tries = 1;

    public int $timeout = 600;

    public function uniqueId(): string
    {
        return 'catalog-zero-out-stale';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(ZeroOutStaleProductsAction $action): void
    {
        event(new CatalogStaleProductsZeroed($action()));
    }

    public function failed(Throwable $e): void
    {
        Log::error('Не удалось обнулить товары, отсутствующие в последней выгрузке 1С', [
            'error' => $e->getMessage(),
        ]);
    }
}
