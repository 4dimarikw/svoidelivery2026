<?php

declare(strict_types=1);

namespace Domain\Order\Observers;

use Domain\Catalog\Actions\UpdateAmountOrder;
use Domain\Order\Models\OrderItem;

class OrderItemObserver
{
    public function created(OrderItem $orderItem): void
    {
        app(UpdateAmountOrder::class)->execute($orderItem->order);
    }

    public function updated(OrderItem $orderItem): void
    {
        if ($orderItem->wasChanged('quantity')) {
            app(UpdateAmountOrder::class)->execute($orderItem->order);
        }
    }

    public function deleted(OrderItem $orderItem): void
    {
        app(UpdateAmountOrder::class)->execute($orderItem->order);
    }
}
