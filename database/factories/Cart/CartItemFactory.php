<?php

declare(strict_types=1);

namespace Database\Factories\Cart;

use Database\Factories\Catalog\ProductFactory;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => ProductFactory::new(),
            // PriceCast принимает скаляр в рублях (decimal(12,2), как products.price).
            'price' => fake()->randomFloat(2, 50, 5000),
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
