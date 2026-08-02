<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\BeerProductDetailFactory;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $product_id
 * @property int|null $beer_style_id
 * @property int|null $untappd_beer_id
 * @property numeric|null $abv
 * @property numeric|null $ibu
 * @property numeric|null $plato
 * @property numeric|null $ebc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BeerStyle|null $beerStyle
 * @property-read Product $product
 * @property-read UntappdBeer|null $untappdBeer
 *
 * @method static \Database\Factories\Catalog\BeerProductDetailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereAbv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereBeerStyleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereEbc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereIbu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail wherePlato($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereUntappdBeerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeerProductDetail whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BeerProductDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'beer_style_id',
        'untappd_beer_id',
        'abv',
        'ibu',
        'plato',
        'ebc',
    ];

    protected function casts(): array
    {
        return [
            'abv' => 'decimal:2',
            'ibu' => 'decimal:2',
            'plato' => 'decimal:2',
            'ebc' => 'decimal:2',
        ];
    }

    protected static function newFactory(): Factory
    {
        return BeerProductDetailFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function beerStyle(): BelongsTo
    {
        return $this->belongsTo(BeerStyle::class);
    }

    public function untappdBeer(): BelongsTo
    {
        return $this->belongsTo(UntappdBeer::class);
    }
}
