<?php

namespace App\Events\Order;

use App\Events\LoggableEvent;

/**
 * Сбой выгрузки заказа на FTP 1С — попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * CatalogStaleProductsZeroed/VKSyncFailed).
 */
final readonly class OrderFtpUploadFailed implements LoggableEvent
{
    public function __construct(
        public int $orderId,
        public string $reason, // order_not_found | upload_failed | queue_exhausted
        public ?string $exceptionClass = null,
        public ?string $errorMessage = null,
        public bool $queued = false, // сбой инлайн-попыток, но отложенный job поставлен
    ) {}

    public function eventType(): string
    {
        return 'order.ftp_upload_failed';
    }

    /**
     * warning, а не error, когда отложенный job поставлен — выгрузка ещё
     * может пройти сама в окне ретраев (config('order.ftp_upload.queue')).
     */
    public function level(): string
    {
        return $this->queued ? 'warning' : 'error';
    }

    public function message(): string
    {
        return match ($this->reason) {
            'order_not_found' => "Заказ #{$this->orderId} не найден — выгрузка на FTP отменена.",
            'queue_exhausted' => "Заказ #{$this->orderId} не выгружен на FTP после всех попыток очереди: {$this->errorMessage}.",
            default => $this->queued
                ? "Заказ #{$this->orderId} не выгружен на FTP: {$this->errorMessage}. Поставлен в очередь на повтор."
                : "Заказ #{$this->orderId} не выгружен на FTP: {$this->errorMessage}.",
        };
    }

    public function context(): array
    {
        return [
            'order_id' => $this->orderId,
            'reason' => $this->reason,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
            'queued' => $this->queued,
        ];
    }
}
