<?php

namespace Domain\Untappd\Models;

use Database\Factories\Catalog\UntappdBeerFactory;
use Domain\Catalog\Models\BeerProductDetail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
