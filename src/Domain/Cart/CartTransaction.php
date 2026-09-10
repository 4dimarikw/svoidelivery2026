<?php

declare(strict_types=1);

namespace Domain\Cart;

use Closure;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

final class CartTransaction
{
    public function run(int $userId, Closure $callback, array $productIds = [], ?int $cartId = null): mixed
    {
        return DB::transaction(function () use ($userId, $callback, $productIds, $cartId) {
            if (User::query()->whereKey($userId)->lockForUpdate()->first() === null) {
                return null;
            }

            $targetCartId = $cartId ?? Cart::query()->firstOrCreate(['user_id' => $userId])->id;
            $cart = Cart::query()->whereKey($targetCartId)->where('user_id', $userId)->lockForUpdate()->first();
            if ($cart === null) {
                return null;
            }

            $items = $cart->cartItems()->orderBy('id')->lockForUpdate()->get()->keyBy('product_id');
            $ids = $items->keys()->merge($productIds)->unique()->sort()->values();
            $products = Product::query()->whereKey($ids->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $products->load([
                'category' => fn ($query) => $query->orderBy('id')->sharedLock(),
                'manufacturer' => fn ($query) => $query->orderBy('id')->sharedLock(),
            ]);
            foreach ($items as $item) {
                $item->setRelation('product', $products->get($item->product_id));
            }

            return $callback($cart, $items, $products);
        }, 3);
    }
}
