<?php

declare(strict_types=1);

namespace Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Support\ValueObjects\Price;

/**
 * Каст decimal-колонки (рубли, напр. products.price/cart_items.price, оба
 * decimal(12,2)) в Support\ValueObjects\Price (копейки внутри) и обратно.
 * Price разбирает десятичные строки и хранит копейки; cast проверяет диапазон
 * decimal(12,2) и записывает точную строку без промежуточного float.
 *
 * @implements CastsAttributes<Price, Price|int|float|string|null>
 */
final class PriceCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Price
    {
        return $value === null ? null : Price::fromMajor($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Price && ! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new \InvalidArgumentException('Invalid monetary amount type.');
        }
        $price = $value instanceof Price ? $value : Price::fromMajor($value);

        return $price->decimal();
    }
}
