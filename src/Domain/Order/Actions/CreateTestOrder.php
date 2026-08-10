<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use Domain\Order\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Создание тестового заказа для конкретного пользователя (кнопка
 * "Создать тестовый заказ" в админке, см. OrderIndexPage). Сумма заказа
 * не проставляется вручную — её пересчитывает OrderItemObserver на
 * OrderItem::created (единый источник истины, см. UpdateAmountOrder).
 */
final class CreateTestOrder
{
    /**
     * @throws Throwable
     */
    public function __invoke(int $userId): Order
    {
        return $this->execute($userId);
    }

    /**
     * @throws Throwable
     */
    public function execute(int $userId): Order
    {
        return DB::transaction(function () use ($userId) {
            $user = User::query()->findOrFail($userId);
            $profile = $user->profile;
            $address = $user->addresses()->where('is_default', true)->first()
                ?? $user->addresses()->first();

            $order = Order::query()->create([
                'user_id' => $user->id,
                'delivery_type_id' => 1,
                'payment_method_id' => 1,
                'comment' => 'Тестовый заказ',
                'amount' => 0,
            ]);

            OrderCustomer::query()->create([
                'order_id' => $order->id,
                'first_name' => $profile?->first_name ?? 'Тест',
                'last_name' => $profile?->last_name ?? 'Тестов',
                'phone' => $profile?->phone ?? '79990000000',
                'city' => $address?->city,
                'address' => $address?->address,
                'comment' => $address?->comment,
            ]);

            $products = Product::query()
                ->published()
                ->inRandomOrder()
                ->limit(random_int(1, 3))
                ->get();

            foreach ($products as $product) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => random_int(1, 5),
                ]);
            }

            return $order->fresh();
        });
    }
}
