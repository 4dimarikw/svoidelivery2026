<?php

namespace Domain\Order\Models;

use Database\Factories\Order\DeliveryTypeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
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

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return DeliveryTypeFactory::new();
    }
}
