<?php

declare(strict_types=1);

namespace Domain\Cart\Models;

use Database\Factories\Cart\CartFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") — дефолтная
     * конвенция фабрик её не резолвит, та же ловушка, что уже задокументирована
     * в Domain\Favorite\Models\Favorite::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return CartFactory::new();
    }
}
