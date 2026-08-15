<?php

namespace Domain\Order\Models;

use Database\Factories\Order\OrderFactory;
use Domain\Auth\Models\User;
use Domain\Order\Enums\OrderStatuses;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
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
            $order->number = 'SD-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
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

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
