<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\CategoryFactory;
use Domain\Catalog\Filters\FilterOptionsRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Services\CatalogImport\CategoryRegistry;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property bool $expects_container
 * @property bool $expects_volume
 * @property bool $price_exempt
 * @property bool $name_from_article
 * @property string|null $default_brand
 * @property string|null $container_code
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
        'expects_container',
        'expects_volume',
        'price_exempt',
        'name_from_article',
        'default_brand',
        'container_code',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expects_container' => 'boolean',
            'expects_volume' => 'boolean',
            'price_exempt' => 'boolean',
            'name_from_article' => 'boolean',
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
     * CategoryRegistry кеширует категории. События изменения модели очищают этот кеш и
     * отдельный кеш витринных фильтров FilterOptionsRegistry.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            CategoryRegistry::flush();
            FilterOptionsRegistry::flush();
        });
        static::deleted(function () {
            CategoryRegistry::flush();
            FilterOptionsRegistry::flush();
        });
    }

    /**
     * Slug служит ключом категории для CategorySlugResolver и импорта. Начальные значения задаются миграциями и
     * CategorySeeder. preventOverwrite() разрешает генерацию только при отсутствии slug и сохраняет заданный
     * ключ при изменении названия.
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
