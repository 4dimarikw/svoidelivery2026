<?php

namespace Domain\Untappd\Models;

use Database\Factories\Catalog\UntappdBeerFactory;
use Domain\Catalog\Models\BeerProductDetail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $beer_id
 * @property string|null $name
 * @property string|null $brewery
 * @property string|null $style
 * @property int $rating_count
 * @property numeric|null $rating_score
 * @property string|null $label
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BeerProductDetail> $beerProductDetails
 * @property-read int|null $beer_product_details_count
 * @method static \Database\Factories\Catalog\UntappdBeerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereBeerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereBrewery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereRatingCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereRatingScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereStyle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UntappdBeer whereUrl($value)
 * @mixin \Eloquent
 */
class UntappdBeer extends Model
{
    use HasFactory;

    protected $fillable = [
        'beer_id',
        'name',
        'brewery',
        'style',
        'rating_count',
        'rating_score',
        'label',
        'url',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_score' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return UntappdBeerFactory::new();
    }

    public function beerProductDetails(): HasMany
    {
        return $this->hasMany(BeerProductDetail::class);
    }
}
