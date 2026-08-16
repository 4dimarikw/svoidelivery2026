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
            // Не только флаг in_stock — количество в корзине могло превысить
            // фактический остаток (частично раскуплен другим заказом между
            // добавлением в корзину и оформлением), CartManager::clampQuantity()
            // тут не участвует. Product::availableStock() — единственный
            // источник истины про доступное количество, см. Domain\Cart\CartManager.
            if ($item->quantity > $item->product->availableStock()) {
                throw new OrderProcessException(__('order.errors.out_of_stock', ['title' => $item->product->name]));
            }
        }

        return $next($order);
    }
}
