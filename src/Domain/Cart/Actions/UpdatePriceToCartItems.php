<?php

declare(strict_types=1);

namespace Domain\Cart\Actions;

use Domain\Cart\Models\CartItem;
use Domain\Product\Models\Product;

final class UpdatePriceToCartItems
{
    public function __invoke(int $productId): void
    {
        $this->handle($productId);
    }

    public function handle(int $productId): void
    {
        $product = Product::query()->select(['id', 'price'])->where('id', $productId)->first();

        $price = $product->price->raw();

        CartItem::query()->where('product_id', $productId)->update(['price' => $price]);
    }
}
