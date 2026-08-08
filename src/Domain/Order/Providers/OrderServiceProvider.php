<?php

declare(strict_types=1);

namespace Domain\Order\Providers;

use Domain\Order\Models\OrderItem;
use Domain\Order\Observers\OrderItemObserver;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Пересчёт Order::amount при любом создании/правке количества/
        // удалении позиции — единый источник истины (см. UpdateAmountOrder),
        // AssignProducts сам amount не проставляет.
        OrderItem::observe(OrderItemObserver::class);
    }
}
