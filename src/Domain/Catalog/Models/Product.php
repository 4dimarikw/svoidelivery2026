<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ProductFactory;
use Domain\Catalog\Enums\ProductStatus;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Infrastructure\Settings\GeneralSettings;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Support\Casts\HtmlEntityDecoder;

/**
 * @property int $id
 * @property string $external_code
 * @property string|null $article
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $category_id
 * @property int|null $manufacturer_id
 * @property int|null $volume_id
 * @property int|null $container_id
 * @property numeric $price
 * @property int $stock_quantity
 * @property bool $in_stock
 * @property int|null $package_units
 * @property string|null $packaging_raw
 * @property string|null $source_category_path
 * @property int|null $shelf_life_days
 * @property string|null $brand
 * @property ProductStatus $status
 * @property array|null $flags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BeerProductDetail|null $beerDetails
 * @property-read Category $category
 * @property-read Container|null $container
 * @property-read mixed $label
 * @property-read Manufacturer|null $manufacturer
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read mixed $thumb
 * @property-read Volume|null $volume
 *
 * @method static Builder<static>|Product active()
 * @method static ProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|Product inCategory(int $categoryId)
 * @method static Builder<static>|Product inPriceRange(?string $min, ?string $max)
 * @method static Builder<static>|Product newModelQuery()
 * @method static Builder<static>|Product newQuery()
 * @method static Builder<static>|Product query()
 * @method static Builder<static>|Product whereArticle($value)
 * @method static Builder<static>|Product whereBrand($value)
 * @method static Builder<static>|Product whereCategoryId($value)
 * @method static Builder<static>|Product whereContainerId($value)
 * @method static Builder<static>|Product whereCreatedAt($value)
 * @method static Builder<static>|Product whereDescription($value)
 * @method static Builder<static>|Product whereExternalCode($value)
 * @method static Builder<static>|Product whereId($value)
 * @method static Builder<static>|Product whereInStock($value)
 * @method static Builder<static>|Product whereStatus($value)
 * @method static Builder<static>|Product whereManufacturerId($value)
 * @method static Builder<static>|Product whereName($value)
 * @method static Builder<static>|Product wherePackageUnits($value)
 * @method static Builder<static>|Product wherePackagingRaw($value)
 * @method static Builder<static>|Product wherePrice($value)
 * @method static Builder<static>|Product whereShelfLifeDays($value)
 * @method static Builder<static>|Product whereSlug($value)
 * @method static Builder<static>|Product whereSourceCategoryPath($value)
 * @method static Builder<static>|Product whereStockQuantity($value)
 * @method static Builder<static>|Product whereUpdatedAt($value)
 * @method static Builder<static>|Product whereVolumeId($value)
 *
 * @mixin Eloquent
 */
class Product extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;

    protected $fillable = [
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
        'status',
        'flags',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'status' => ProductStatus::class,
            'flags' => 'json',
            'article' => HtmlEntityDecoder::class,
            'description' => HtmlEntityDecoder::class,
            'name' => HtmlEntityDecoder::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Product $product) => $product->name ?: $product->article)
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
            get: fn () => $this->resolveMediaUrl('label')
        );
    }

    protected function thumb(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolveMediaUrl('thumb')
        );
    }

    private function resolveMediaUrl(string $conversion): string
    {
        $url = $this->getFirstMedia('main')?->getUrl($conversion);

        if (! $url || Str::contains($url, 'badge-beer-default-thumb')) {
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

    public function beerDetails(): HasOne
    {
        return $this->hasOne(BeerProductDetail::class);
    }

    protected function isNew(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->created_at !== null
                && $this->created_at->gt(now()->subDays(app(GeneralSettings::class)->new_days))
        );
    }

    public function scopeNew($query)
    {
        return $query->where('created_at', '>', now()->subDays(7));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::PUBLISHED)->where('in_stock', true);
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
