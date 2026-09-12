<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ManufacturerFactory;
use Domain\Catalog\Filters\FilterOptionsRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string $slug
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 *
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
 *
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
     * Суффиксы slug начинаются с 2: name, name-2, name-3. При обновлении slug не пересоздаётся. normalized_name
     * служит ключом firstOrCreate() при импорте и сохраняет идентичность записи после переименования
     * администратором.
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
     * normalized_name обязателен и уникален. Импорт задаёт его явно; обработчик creating заполняет значение для
     * админки, фабрик и других вызовов. При обновлении ключ сохраняется, чтобы импорт находил ту же запись.
     */
    protected static function booted(): void
    {
        static::creating(function (Manufacturer $manufacturer) {
            if (blank($manufacturer->normalized_name)) {
                $manufacturer->normalized_name = normalize_name($manufacturer->name);
            }
        });

        // Значения этого справочника кешируются целиком в FilterOptionsRegistry —
        // см. её докблок.
        static::saved(fn () => FilterOptionsRegistry::flush());
        static::deleted(fn () => FilterOptionsRegistry::flush());
    }
}
