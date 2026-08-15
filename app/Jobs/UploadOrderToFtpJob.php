<?php

namespace App\Jobs;

use App\Events\Order\OrderFtpUploadFailed;
use Domain\Order\Actions\UploadOrderToFTP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Отложенная выгрузка заказа на FTP 1С.
 *
 * Диспатчится из UploadOrderToFTP::execute(), когда все инлайн-попытки
 * (see config('order.ftp_upload.max_attempts')) исчерпаны — например, при
 * затяжном сбое FTP-сервера. afterCommit = true обязателен: диспатч
 * происходит из OrderProcess::run() внутри транзакции создания заказа.
 */
class UploadOrderToFtpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /**
     * @var array<int, int>
     */
    public array $backoff;

    public int $timeout;

    public function __construct(public int $orderId)
    {
        $this->tries = config('order.ftp_upload.queue.tries', 5);
        $this->backoff = config('order.ftp_upload.queue.backoff', [60, 120, 300, 600]);
        $this->timeout = config('order.ftp_upload.queue.timeout', 120);

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
        return config('order.ftp_upload.queue.unique_for', 3600);
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
        // Терминальный провал: очередь исчерпала все попытки, дальше
        // выгрузка сама не повторится — только ручной resend из MoonShine.
        event(new OrderFtpUploadFailed(
            orderId: $this->orderId,
            reason: 'queue_exhausted',
            exceptionClass: $e::class,
            errorMessage: $e->getMessage(),
        ));
    }
}
