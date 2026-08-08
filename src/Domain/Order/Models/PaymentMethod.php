<?php

namespace Domain\Order\Models;

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
}
