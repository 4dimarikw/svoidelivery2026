<?php

declare(strict_types=1);

namespace Database\Factories\Order;

use Database\Factories\Catalog\ProductFactory;
use Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 *
 * Создание строки запускает observer в Domain\Order\Providers\OrderServiceProvider,
 * который пересчитывает orders.amount — итоговая сумма заказа не совпадёт с тем,
 * что было задано при Order::factory()->create(['amount' => ...]) до этого.
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderFactory::new(),
            'product_id' => ProductFactory::new(),
            'price' => fake()->randomFloat(2, 100, 5000),
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}
