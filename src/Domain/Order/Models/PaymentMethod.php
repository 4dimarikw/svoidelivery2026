<?php

namespace Domain\Order\Models;

use Database\Factories\Order\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'redirect_to_pay',
    ];

    protected $casts = [
        'redirect_to_pay' => 'boolean',
    ];

    public $timestamps = false;

    /**
     * Способ оплаты по умолчанию для /checkout (config('order.default_payment_method')).
     * Тот же фолбэк-принцип, что DeliveryType::default().
     */
    public static function default(): self
    {
        return static::query()->firstWhere('title', config('order.default_payment_method'))
            ?? static::query()->orderBy('id')->firstOrFail();
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return PaymentMethodFactory::new();
    }
}
