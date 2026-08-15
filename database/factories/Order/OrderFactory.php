<?php

declare(strict_types=1);

namespace Database\Factories\Order;

use Database\Factories\UserFactory;
use Domain\Order\Enums\OrderStatuses;
use Domain\Order\Models\DeliveryType;
use Domain\Order\Models\Order;
use Domain\Order\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Не задаём 'number' — Order::booted() генерирует его сам в
            // creating-хуке и переписал бы любое значение отсюда.
            'user_id' => UserFactory::new(),
            // Переиспользуем сидированные миграцией строки (delivery_types.title
            // и payment_methods — небольшие справочники), а не плодим новые
            // на каждый вызов фабрики.
            'delivery_type_id' => fn () => DeliveryType::query()->value('id') ?? DeliveryType::factory(),
            'payment_method_id' => fn () => PaymentMethod::query()->value('id') ?? PaymentMethod::factory(),
            'comment' => null,
            'amount' => 0,
            'status' => OrderStatuses::New->value,
        ];
    }
}
