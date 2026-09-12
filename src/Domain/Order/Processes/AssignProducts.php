<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Cart\Models\CartItem;
use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\DTO\OrderItemDTO;
use Domain\Order\Models\Order;

final class AssignProducts implements OrderProcessContract
{
    public function handle(Order $order, $next)
    {
        // Сумму заказа пересчитывает OrderItemObserver через UpdateAmountOrder при создании каждой позиции.
        foreach (cart()->cartItems() as $cartItem) {
            /** @var CartItem $cartItem */
            $item = $order->orderItems()->make(OrderItemDTO::fromCartItem($cartItem)->toArray());
            $item->setRelation('product', $cartItem->product);
            $item->save();
        }

        return $next($order);
    }
}
