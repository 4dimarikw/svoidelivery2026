<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\BeerStyleFactory;
use Domain\Catalog\Filters\FilterOptionsRegistry;
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
        static::creating(function (BeerStyle $beerStyle) {
            if (blank($beerStyle->normalized_name)) {
                $beerStyle->normalized_name = normalize_name($beerStyle->name);
            }
        });

        // Значения этого справочника кешируются целиком в FilterOptionsRegistry —
        // см. её докблок.
        static::saved(fn () => FilterOptionsRegistry::flush());
        static::deleted(fn () => FilterOptionsRegistry::flush());
    }
}
