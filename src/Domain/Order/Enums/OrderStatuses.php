<?php

declare(strict_types=1);

namespace Domain\Order\Enums;

use Domain\Order\Models\Order;
use Domain\Order\States\CancelledOrderState;
use Domain\Order\States\CompletedOrderState;
use Domain\Order\States\NewOrderState;
use Domain\Order\States\OrderState;
use Domain\Order\States\PaidOrderState;
use Domain\Order\States\PendingOrderState;
use Domain\Order\States\SentOrderState;

enum OrderStatuses: string
{
    case New = 'new';
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Sent = 'sent';

    public function createState(Order $order): OrderState
    {
        return match ($this) {
            OrderStatuses::New => new NewOrderState($order),
            OrderStatuses::Pending => new PendingOrderState($order),
            OrderStatuses::Paid => new PaidOrderState($order),
            OrderStatuses::Cancelled => new CancelledOrderState($order),
            OrderStatuses::Completed => new CompletedOrderState($order),
            OrderStatuses::Sent => new SentOrderState($order),
        };
    }

    public function toString(): ?string
    {
        return __('order.statuses.'.$this->value);
    }
}
