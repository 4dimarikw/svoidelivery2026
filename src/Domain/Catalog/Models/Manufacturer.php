<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ManufacturerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string $slug
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Domain\Catalog\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Database\Factories\Catalog\ManufacturerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereNormalizedName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Manufacturer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Manufacturer extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
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
        return ManufacturerFactory::new();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
     * the import (ResolveBrandStage) always supplies it explicitly, so this
     * only fills gaps left by other callers (admin CRUD, factories,
     * tinker). Create-only, for the same reason as the slug above: it's the
     * import's firstOrCreate() matching key, so recomputing it on an update
     * (e.g. an admin rename) would make the next import miss this row and
     * insert a duplicate instead of matching it.
     */
    protected static function booted(): void
    {
        static::creating(function (Manufacturer $manufacturer) {
            if (blank($manufacturer->normalized_name)) {
                $manufacturer->normalized_name = normalize_name($manufacturer->name);
            }
        });
    }
}
