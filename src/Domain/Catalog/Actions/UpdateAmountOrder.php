<?php

namespace Domain\Catalog\Actions;

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
        // Суммируем в копейках (minor) — без плавающей точки, тот же
        // приём, что CartManager::amount().
        $newAmount = Price::fromMinor(
            $order->orderItems->sum(fn ($orderItem) => $orderItem->amount->minor())
        );

        $order->amount = $newAmount;

        if ($order->isDirty()) {
            $order->save();
        }

        return $order;
    }
}
