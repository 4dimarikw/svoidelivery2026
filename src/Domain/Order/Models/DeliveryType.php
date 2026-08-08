<?php

namespace Domain\Order\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Support\Casts\PriceCast;

class DeliveryType extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'with_address',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'with_address' => 'boolean',
    ];

    /**
     * Способ доставки по умолчанию для /checkout (config('order.default_delivery_type')).
     * Фолбэк на первую запись — если админ переименовал строку в справочнике,
     * оформление заказа не должно упасть.
     */
    public static function default(): self
    {
        return static::query()->firstWhere('title', config('order.default_delivery_type'))
            ?? static::query()->orderBy('id')->firstOrFail();
    }
}
