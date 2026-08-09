<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\VolumeFactory;
use Domain\Catalog\Filters\FilterOptionsRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $milliliters
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 *
 * @method static \Database\Factories\Catalog\VolumeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume whereMilliliters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Volume whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Volume extends Model
{
    use HasFactory;

    protected $fillable = [
        'milliliters',
        'label',
    ];

    protected static function newFactory(): Factory
    {
        return VolumeFactory::new();
    }

    /**
     * Значения этого справочника кешируются целиком в FilterOptionsRegistry —
     * см. её докблок.
     */
    protected static function booted(): void
    {
        static::saved(fn () => FilterOptionsRegistry::flush());
        static::deleted(fn () => FilterOptionsRegistry::flush());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
