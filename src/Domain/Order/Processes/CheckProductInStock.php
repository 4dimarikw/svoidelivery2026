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
            // Модели и справочники заново прочитаны под блокировками CartManager.
            if (! $item->product->canBePurchased($item->quantity)) {
                $message = $item->product->isVisibleOnStorefront()
                    ? 'order.errors.out_of_stock'
                    : 'order.errors.product_unavailable';
                throw new OrderProcessException(__($message, ['title' => $item->product->name]));
            }
        }

        return $next($order);
    }
}
