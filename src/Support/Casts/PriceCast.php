<?php

declare(strict_types=1);

namespace Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Support\ValueObjects\Price;

/**
 * Каст decimal-колонки (рубли, напр. products.price/cart_items.price, оба
 * decimal(12,2)) в Support\ValueObjects\Price (копейки внутри) и обратно.
 * Единственное место, где происходит эта конвертация — сам Price о рублях
 * ничего не знает, работает только в минорных единицах.
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

        $price = $value instanceof Price ? $value : Price::fromMajor($value);

        return number_format($price->major(), 2, '.', '');
    }
}
