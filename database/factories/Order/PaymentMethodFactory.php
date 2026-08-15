<?php

declare(strict_types=1);

namespace Database\Factories\Order;

use Domain\Order\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'redirect_to_pay' => false,
        ];
    }

    /**
     * Онлайн-оплата — оформление заказа завершается редиректом на оплату.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'redirect_to_pay' => true,
        ]);
    }
}
