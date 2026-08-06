<?php

namespace Domain\Cart\Collections;

use Domain\Cart\Resources\CartItemResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as IlluminateCollection;

class CartItemCollection extends Collection
{
    public function asResource(): self|IlluminateCollection
    {
        return $this->map(function ($cartItem) {
            return CartItemResource::make($cartItem)->resolve();
        });
    }

    /**
     * Получить общую сумму всех элементов корзины
     */
    public function totalAmount(): int
    {
        return $this->sum(fn ($cartItem) => $cartItem->amount->raw());
    }

    /**
     * Получить общее количество всех элементов корзины
     */
    public function totalQuantity(): int
    {
        return $this->sum('quantity');
    }
}
