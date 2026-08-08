<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Cart\Models\CartItem;
use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\Exceptions\OrderProcessException;
use Domain\Order\Models\Order;

final class CheckProductInStock implements OrderProcessContract
{
    /**
     * @throws OrderProcessException
     */
    public function handle(Order $order, $next)
    {
        /** @var CartItem $item */
        foreach (cart()->cartItems() as $item) {
            if (! $item->product->in_stock) {
                throw new OrderProcessException(__('order.errors.out_of_stock', ['title' => $item->product->name]));
            }
        }

        return $next($order);
    }
}
