<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\Models\Order;

final class PersistOrder implements OrderProcessContract
{
    public function handle(Order $order, $next)
    {
        $order->save();

        return $next($order);
    }
}
