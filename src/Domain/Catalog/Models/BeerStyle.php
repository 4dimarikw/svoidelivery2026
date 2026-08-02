<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\BeerStyleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class BeerStyle extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'parent_id',
        'name',
        'normalized_name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return BeerStyleFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function beerProductDetails(): HasMany
    {
        return $this->hasMany(BeerProductDetail::class);
    }

    /**
     * Suffix numbering starts at 2 ("name", "name-2", "name-3", ...) to match
     * this project's existing slug convention elsewhere. Update-time
     * regeneration is disabled: normalized_name (see booted() below) is the
     * import's firstOrCreate() matching key, and slug is left alone for the
     * same reason an admin rename shouldn't silently change it — the row
     * should still be found and updated, not re-created, on the next import.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->startSlugSuffixFrom(2)
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * `normalized_name` is NOT NULL UNIQUE but nothing else generates it —
     * the import (ResolveBeerStyleStage) always supplies it explicitly, so
     * this only fills gaps left by other callers (admin CRUD, factories,
     * tinker). Create-only, for the same reason as the slug above: it's the
     * import's firstOrCreate() matching key, so recomputing it on an update
     * (e.g. an admin rename) would make the next import miss this row and
     * insert a duplicate instead of matching it.
     */
    protected static function booted(): void
    {
        static::creating(function (BeerStyle $beerStyle) {
            if (blank($beerStyle->normalized_name)) {
                $beerStyle->normalized_name = normalize_name($beerStyle->name);
            }
        });
    }
}
