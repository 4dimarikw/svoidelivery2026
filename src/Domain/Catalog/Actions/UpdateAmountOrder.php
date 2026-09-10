<?php

namespace Domain\Catalog\Actions;

use Domain\Order\Actions\CheckedOrderTotal;
use Domain\Order\Models\Order;
use Exception;
use Support\ValueObjects\Price;

class UpdateAmountOrder
{
    /**
     * @throws Exception
     */
    public function __invoke(Order $order): Order
    {
        return $this->execute($order);
    }

    public function execute(Order $order): Order
    {
        return $order->getConnection()->transaction(function () use ($order): Order {
            $fresh = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            // Текущее блокирующее чтение, включая REPEATABLE READ; кеш связи не используется.
            $items = $fresh->orderItems()->orderBy('id')->lockForUpdate()->get();
            $fresh->amount = Price::fromMinor(CheckedOrderTotal::minor(
                $items->map(fn ($item) => ['price' => $item->price, 'quantity' => $item->quantity])
            ));
            if ($fresh->isDirty('amount')) {
                $fresh->save();
            }
            $order->amount = $fresh->amount;
            $order->syncOriginalAttribute('amount');
            $order->setRelation('orderItems', $items);

            return $order;
        });
    }
}
