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
            // in_stock тоже пишем здесь, а не только stock_quantity — иначе
            // товар, раскупленный в ноль, остаётся "в наличии" по флагу и
            // расходится с фактическим остатком (Product::availableStock()),
            // см. Domain\Cart\CartManager::clampQuantity(). Eloquent::update(),
            // не query-builder: in_stock входит в Product::SEO_WATCHED_ATTRIBUTES,
            // только так поднимется хук пересинка SEO.
            $item->product->update([
                'stock_quantity' => $newStockQuantity,
                'in_stock' => $newStockQuantity > 0,
            ]);
        }

        return $next($order);
    }
}
