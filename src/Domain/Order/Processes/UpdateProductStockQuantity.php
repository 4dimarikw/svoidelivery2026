<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Cart\Models\CartItem;
use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\Models\Order;

final class UpdateProductStockQuantity implements OrderProcessContract
{
    public function handle(Order $order, $next)
    {
        /** @var CartItem $item */
        foreach (cart()->cartItems() as $item) {
            $newStockQuantity = max(0, $item->product->stock_quantity - $item->quantity);
            $item->product->update(['stock_quantity' => $newStockQuantity]);
        }

        return $next($order);
    }
}
