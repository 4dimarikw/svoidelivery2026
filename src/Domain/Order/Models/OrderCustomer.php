<?php

namespace Domain\Order\Models;

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
        'street',
        'house',
        'apartment',
        'entrance',
        'floor',
        'intercom',
        'comment',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
