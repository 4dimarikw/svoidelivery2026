<?php

namespace App\Jobs;

use Domain\Order\Actions\UploadOrderToFTP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Отложенная выгрузка заказа на FTP 1С.
 *
 * Диспатчится из UploadOrderToFTP::execute(), когда все инлайн-попытки
 * (see UploadOrderToFTP::MAX_ATTEMPTS) исчерпаны — например, при затяжном
 * сбое FTP-сервера. afterCommit = true обязателен: диспатч происходит из
 * OrderProcess::run() внутри транзакции создания заказа.
 */
class UploadOrderToFtpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 300, 600];

    public int $timeout = 120;

    public function __construct(public int $orderId)
    {
        // Метод, не property-переопределение — Queueable уже объявляет
        // $afterCommit сам, а PHP 8.4 не даёт классу передекларировать
        // унаследованное от трейта свойство с другим значением по
        // умолчанию (fatal "definition differs and is considered
        // incompatible").
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // queueOnFailure: false — иначе провал последней попытки job'а
        // снова поставит job в очередь и получится дублирование ретраев.
        if (! (new UploadOrderToFTP)->execute($this->orderId, queueOnFailure: false)) {
            throw new RuntimeException("Не удалось выгрузить заказ {$this->orderId} на FTP");
        }
    }

    public function failed(Throwable $e): void
    {
        // Log::error(), не Log::channel('database') — такого канала нет в
        // проекте (стандартный stack используется везде).
        Log::error('Не удалось выгрузить заказ на FTP после всех попыток очереди', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
