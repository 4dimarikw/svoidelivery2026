<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\BeerProductDetailFactory;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
