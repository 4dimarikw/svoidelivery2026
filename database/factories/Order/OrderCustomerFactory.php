<?php

declare(strict_types=1);

namespace Database\Factories\Order;

use Domain\Order\Models\OrderCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCustomer>
 */
class OrderCustomerFactory extends Factory
{
    protected $model = OrderCustomer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderFactory::new(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+7 9## ###-##-##'),
            'messenger_url' => 'https://t.me/'.fake()->userName(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'comment' => null,
        ];
    }

    /**
     * Самовывоз — адресных полей нет (см. OrderCustomer, "пусто целиком
     * у доставки без адреса").
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => null,
            'address' => null,
        ]);
    }
}
