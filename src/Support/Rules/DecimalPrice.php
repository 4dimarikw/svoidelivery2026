<?php

declare(strict_types=1);

namespace Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Support\ValueObjects\Price;

final class DecimalPrice implements ValidationRule
{
    public function __construct(private readonly int $minimumMinor = 0) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException;
            }
            if (Price::fromMajor($value)->minor() < $this->minimumMinor) {
                throw new InvalidArgumentException;
            }
        } catch (InvalidArgumentException) {
            $fail(__('order.errors.invalid_price', ['min' => Price::fromMinor($this->minimumMinor)->decimal()]));
        }
    }
}
