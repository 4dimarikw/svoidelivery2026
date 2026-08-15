<?php

namespace Domain\Order\Models;

use Database\Factories\Order\OrderCustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Снимок покупателя и адреса доставки на момент оформления заказа —
 * не живая ссылка на Domain\Profile\Models\Address (та же логика, что уже
 * есть у CartItem::price: значение фиксируется в момент действия, заказ не
 * должен «поехать», если покупатель потом отредактирует/удалит адрес).
 * Адресные поля пустые целиком у доставки без адреса (Order::deliveryType
 * с with_address = false, самовывоз) — см. Domain\Order\Processes\AssignCustomer.
 */
class OrderCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'first_name',
        'last_name',
        'phone',
        'city',
        'address',
        'comment',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return OrderCustomerFactory::new();
    }
}
