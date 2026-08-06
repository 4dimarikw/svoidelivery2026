<?php

namespace Domain\Cart\Models;

use Domain\Cart\Collections\CartItemCollection;
use Domain\Product\Models\ProductVariation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Casts\PriceCast;
use Support\ValueObjects\Price;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_variation_id',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'quantity' => 'integer',
    ];

    public function newCollection(array $models = []): CartItemCollection
    {
        return new CartItemCollection($models);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => Price::make(
                $this->price->raw() * $this->quantity,
                false
            )
        );
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
