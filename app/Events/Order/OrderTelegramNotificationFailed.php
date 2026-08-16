<?php

namespace App\Events\Order;

use App\Events\LoggableEvent;

/**
 * Сбой уведомления о новом заказе в служебную Telegram-группу — попадает
 * в event_logs через App\Listeners\PersistEventLog (автодискавери по
 * LoggableEvent, как и OrderNotificationEmailFailed).
 */
final readonly class OrderTelegramNotificationFailed implements LoggableEvent
{
    public function __construct(
        public int $orderId,
        public ?string $orderNumber,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'order.telegram_notification_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        $label = $this->orderNumber !== null ? "№{$this->orderNumber}" : "#{$this->orderId}";

        return "Заказ {$label}: не удалось отправить уведомление о заказе в Telegram: {$this->errorMessage}.";
    }

    public function context(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
