<?php

namespace App\Events\Order;

use App\Events\LoggableEvent;

/**
 * Оформление заказа не завершилось — попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * OrderFtpUploadFailed/OrderTelegramNotificationFailed). Диспатчится из
 * App\Http\Controllers\OrderController::store() из единственной развилки catch:
 * бизнес-отказ пайплайна (нет в наличии, минимальная сумма и т.п. —
 * Domain\Order\Exceptions\OrderProcessException) и неожиданный сбой (Throwable)
 * различаются через $reason, оба ведут к 422/500 клиенту без единой записи
 * о заказе, если не логировать их здесь.
 */
final readonly class OrderCreationFailed implements LoggableEvent
{
    public function __construct(
        public int $userId,
        public string $reason, // business_rejected | unexpected
        public int $cartItemsCount,
        public string $errorMessage,
        public ?string $exceptionClass = null,
    ) {}

    public function eventType(): string
    {
        return 'order.creation_failed';
    }

    public function level(): string
    {
        return $this->reason === 'business_rejected' ? 'warning' : 'error';
    }

    public function message(): string
    {
        return match ($this->reason) {
            'business_rejected' => "Оформление заказа отклонено: {$this->errorMessage}",
            default => "Оформление заказа не удалось: {$this->errorMessage}",
        };
    }

    public function context(): array
    {
        return [
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'cart_items_count' => $this->cartItemsCount,
            'error_message' => $this->errorMessage,
            'exception_class' => $this->exceptionClass,
        ];
    }
}
