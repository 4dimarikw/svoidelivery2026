<?php

declare(strict_types=1);

namespace Database\Factories\Order;

use Domain\Order\Models\DeliveryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryType>
 */
class DeliveryTypeFactory extends Factory
{
    protected $model = DeliveryType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'price' => 0,
            'with_address' => true,
        ];
    }

    /**
     * Самовывоз — без адреса доставки.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'with_address' => false,
        ]);
    }
}
