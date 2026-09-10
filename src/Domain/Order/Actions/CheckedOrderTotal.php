<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Illuminate\Validation\ValidationException;
use Support\ValueObjects\Price;

final class CheckedOrderTotal
{
    /** @param iterable<array{price: Price, quantity: int}> $lines */
    public static function minor(iterable $lines): int
    {
        $sum = 0;
        foreach ($lines as $line) {
            $minor = $line['price']->minor();
            $quantity = $line['quantity'];
            if ($quantity < 0 || ($minor > 0 && $quantity > intdiv(Price::MAX_MINOR - $sum, $minor))) {
                throw ValidationException::withMessages(['price' => __('order.errors.amount_overflow')]);
            }
            $sum += $minor * $quantity;
        }

        return $sum;
    }
}
