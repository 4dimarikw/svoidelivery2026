<?php

namespace App\Events\Order;

use App\Events\LoggableEvent;

/**
 * Сбой отправки письма о новом заказе — попадает в event_logs через
 * App\Listeners\PersistEventLog. По образцу OrderFtpUploadFailed:
 * ошибка отправки не должна ронять уже оформленный заказ (см.
 * App\Listeners\Order\HandleOrderCreated), но должна быть видна админу.
 */
final readonly class OrderNotificationEmailFailed implements LoggableEvent
{
    public function __construct(
        public int $orderId,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'order.notification_email_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Заказ #{$this->orderId}: не удалось поставить в очередь письмо-уведомление о заказе: {$this->errorMessage}.";
    }

    public function context(): array
    {
        return [
            'order_id' => $this->orderId,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
