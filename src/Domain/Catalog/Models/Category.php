<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @property-read Collection<int, Property> $properties
 * @property-read int|null $properties_count
 *
 * @method static \Database\Factories\Catalog\CategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Category extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'category_properties')
            ->withPivot(['is_required', 'is_filterable', 'is_visible', 'sort_order']);
    }

    /**
     * `slug` is the CatalogImport pipeline's category identity key — it's
     * the config/catalog_import.php registry key, set explicitly by
     * CategorySeeder and matched on by CategorySlugResolver/
     * CatalogImportCommand — never derived from `name`. preventOverwrite()
     * makes generation a pure fallback: it only fires if slug is still
     * null (e.g. a future ad-hoc Category::create() without one), and never
     * touches an already-set slug again, on either create or update — so a
     * CategorySeeder re-run (which updates name/code/sort_order but never
     * slug) can't drift a category's slug away from its config key.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->startSlugSuffixFrom(2)
            ->preventOverwrite();
    }
}
