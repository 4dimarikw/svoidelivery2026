<?php

declare(strict_types=1);

namespace Domain\Cart\Models;

use Database\Factories\Cart\CartItemFactory;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Casts\PriceCast;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'quantity' => 'integer',
    ];

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price?->multiply($this->quantity)
        );
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") — дефолтная
     * конвенция фабрик её не резолвит, та же ловушка, что уже задокументирована
     * в Domain\Favorite\Models\Favorite::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return CartItemFactory::new();
    }
}
