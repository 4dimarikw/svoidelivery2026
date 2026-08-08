<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\Exceptions\OrderProcessException;
use Domain\Order\Models\Order;

final class TermsOrder implements OrderProcessContract
{
    /**
     * @throws OrderProcessException
     */
    public function handle(Order $order, $next)
    {
        // with_address, не сравнение title === 'Самовывоз' — тот же булев
        // флаг уже есть на DeliveryType специально для таких развилок.
        // Самовывоз освобождён от минимальной суммы — доставка ничего не
        // стоит магазину, порог существует именно ради экономики доставки.
        if ($order->deliveryType->with_address && cart()->amount()->major() < 10000.0) {
            throw new OrderProcessException(__('order.errors.min_amount'));
        }

        if (cart()->amount()->major() <= 1.0) {
            throw new OrderProcessException(__('order.errors.min_amount_1'));
        }

        return $next($order);
    }
}
