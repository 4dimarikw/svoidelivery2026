<?php

namespace Domain\Order\Models;

use Domain\Auth\Models\User;
use Domain\Order\Enums\OrderStatuses;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Support\Casts\PriceCast;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->number = 'ORD-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
        });
    }

    protected $fillable = [
        'number',
        'user_id',
        'delivery_type_id',
        'payment_method_id',
        'comment',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => PriceCast::class,
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => OrderStatuses::from($value)->createState($this)
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryType(): BelongsTo
    {
        return $this->belongsTo(DeliveryType::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderCustomer(): HasOne
    {
        return $this->hasOne(OrderCustomer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
