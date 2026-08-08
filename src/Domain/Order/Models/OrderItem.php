<?php

namespace Domain\Order\Models;

use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Casts\PriceCast;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'quantity' => 'integer',
    ];

    /**
     * Та же формула, что уже есть у CartItem::amount() (src/Domain/Cart/Models/CartItem.php) —
     * снятая price-строка не меняется задним числом, живой Product::price её не касается.
     */
    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price?->multiply($this->quantity)
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
