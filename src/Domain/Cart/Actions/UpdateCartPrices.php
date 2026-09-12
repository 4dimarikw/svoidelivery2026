<?php

declare(strict_types=1);

namespace Domain\Cart\Actions;

use Domain\Cart\CartTransaction;
use Domain\Cart\Models\Cart;
use Illuminate\Support\Collection;

final class UpdateCartPrices
{
    public function handle(int $cartId): void
    {
        $userId = Cart::query()->whereKey($cartId)->value('user_id');
        if ($userId === null) {
            return;
        }
        app(CartTransaction::class)->run((int) $userId,
            fn (Cart $cart, Collection $items) => $this->updateLocked($items), cartId: $cartId);
    }

    /**
     * Вызывающий код должен удерживать блокировки CartTransaction. Возвращает описания изменённых цен.
     */
    public function updateLocked(Collection $items): array
    {
        $changes = [];
        foreach ($items as $item) {
            $product = $item->product;
            if ($product === null || $item->price->minor() === $product->price->minor()) {
                continue;
            }
            $changes[] = $product->name.': '.$item->price->format(2).' → '.$product->price->format(2);
            $item->price = $product->price;
            $item->save();
        }

        return $changes;
    }
}
