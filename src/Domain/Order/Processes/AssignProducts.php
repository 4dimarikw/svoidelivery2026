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
        // $order->amount здесь не проставляется — единственный источник
        // истины для суммы заказа теперь Domain\Order\Observers\OrderItemObserver
        // (пересчитывает через UpdateAmountOrder на created каждой позиции),
        // не дублируем то же самое ещё и тут.
        $order->orderItems()
            ->createMany(
                cart()->cartItems()->map(function (CartItem $cartItem) {
                    return OrderItemDTO::fromCartItem($cartItem)->toArray();
                })->values()->all()
            );

        return $next($order);
    }
}
