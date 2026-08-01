<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;

    protected $fillable = [
        'source_uuid',
        'external_code',
        'article',
        'name',
        'description',
        'category_id',
        'manufacturer_id',
        'volume_id',
        'container_id',
        'price',
        'stock_quantity',
        'in_stock',
        'package_units',
        'packaging_raw',
        'source_category_path',
        'shelf_life_days',
        'brand',
        'sales_rating',
        'is_active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    /**
     * `article` (the CSV's `Артикул`) is the slug source rather than `name` —
     * product names run to hundreds of characters. Self-healing so a
     * re-import that renames the product doesn't break existing links.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Product $product) => $product->article ?: $product->name)
            ->saveSlugsTo('slug')
            ->usingSeparator('-')
            ->selfHealing();
    }

    /**
     * Collection name is read from `catalog_import.untappd_image.collection`
     * by PersistProductImageStage — keep the two in sync if this changes.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')
            ->singleFile()
            ->acceptsFile(function (File $file) {
                return in_array($file->mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
            });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->format('webp')
            ->quality(40)
            ->width(234)
            ->nonQueued();

        $this->addMediaConversion('label')
            ->format('webp')
            ->nonQueued();
    }

    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveMediaUrl('label')
        );
    }

    protected function thumb(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveMediaUrl('thumb')
        );
    }

    private function resolveMediaUrl(string $conversion): string
    {
        $url = $this->getFirstMedia('label')?->getUrl($conversion);

        if (!$url || Str::contains($url, 'badge-beer-default-thumb')) {
            return config('project.default_beer_label');
        }

        return $url;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function volume(): BelongsTo
    {
        return $this->belongsTo(Volume::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function beerDetails(): HasOne
    {
        return $this->hasOne(BeerProductDetail::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('in_stock', true);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInPriceRange(Builder $query, ?string $min, ?string $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max));
    }
}
