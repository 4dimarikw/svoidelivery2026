<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ProductFactory;
use Domain\Catalog\Actions\SyncProductSeoAction;
use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Enums\ProductStatus;
use Eloquent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Infrastructure\Settings\CatalogImportSettings;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Support\Casts\HtmlEntityDecoder;
use Support\Casts\PriceCast;
use Support\Casts\PurifiedHtml;
use Support\ValueObjects\Price;

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
 * @property Price|null $price
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
 * @method static ProductFactory factory($count = null, $state = [])
 * @method static ProductBuilder newModelQuery()
 * @method static ProductBuilder newQuery()
 * @method static ProductBuilder query()
 * @method static ProductBuilder whereArticle($value)
 * @method static ProductBuilder whereBrand($value)
 * @method static ProductBuilder whereCategoryId($value)
 * @method static ProductBuilder whereContainerId($value)
 * @method static ProductBuilder whereCreatedAt($value)
 * @method static ProductBuilder whereDescription($value)
 * @method static ProductBuilder whereExternalCode($value)
 * @method static ProductBuilder whereId($value)
 * @method static ProductBuilder whereInStock($value)
 * @method static ProductBuilder whereStatus($value)
 * @method static ProductBuilder whereManufacturerId($value)
 * @method static ProductBuilder whereName($value)
 * @method static ProductBuilder wherePackageUnits($value)
 * @method static ProductBuilder wherePackagingRaw($value)
 * @method static ProductBuilder wherePrice($value)
 * @method static ProductBuilder whereShelfLifeDays($value)
 * @method static ProductBuilder whereSlug($value)
 * @method static ProductBuilder whereSourceCategoryPath($value)
 * @method static ProductBuilder whereStockQuantity($value)
 * @method static ProductBuilder whereUpdatedAt($value)
 * @method static ProductBuilder whereVolumeId($value)
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
            'price' => PriceCast::class,
            'in_stock' => 'boolean',
            'status' => ProductStatus::class,
            'flags' => 'json',
            'article' => HtmlEntityDecoder::class,
            // PurifiedHtml, не HtmlEntityDecoder — description рендерится как
            // HTML на странице товара (pages/product.blade.php), а не текстом,
            // источник (1С-импорт + свободный Textarea в MoonShine) не доверенный.
            'description' => PurifiedHtml::class.':product_description',
            'name' => HtmlEntityDecoder::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    /**
     * Query-скоупы вынесены в ProductBuilder — см. этот класс, не Product.
     */
    public function newEloquentBuilder($query): ProductBuilder
    {
        return new ProductBuilder($query);
    }

    /**
     * Автоматика для lee-to/laravel-seo-by-url живёт в SyncProductSeoAction
     * (title/description/keywords/OG/JSON-LD — см. Domain\Catalog\Actions),
     * не здесь — модель только решает, КОГДА синкать, сборка текста её не
     * касается.
     *
     * wasRecentlyCreated || wasChanged(self::SEO_WATCHED_ATTRIBUTES) — не
     * "пиши всегда на saved()": catalog:import (PersistProductStage) гоняет
     * update(['price'=>..,'stock_quantity'=>..,'in_stock'=>..]) по всему
     * каталогу на каждом повторном импорте (тысячи строк). Ни цена, ни
     * brand не входят ни в один SEO-текст (магазин не занимается оптом —
     * цену/«купить» убрали из шаблона; title строится из name, не brand —
     * см. SyncProductSeoAction), поэтому их в списке нет; но
     * name/in_stock/volume_id/container_id/category_id/manufacturer_id
     * всё ещё влияют на текст (name — везде, наличие — в JSON-LD
     * Offer.availability, остальные — в title/description/JSON-LD name),
     * их приходится наблюдать. wasChanged() всё равно сравнивает значения,
     * а не факт наличия ключа в update(): повторный импорт с теми же
     * значениями по-прежнему не пишет ничего.
     *
     * Известное ограничение: PersistProductStage создаёт Product раньше,
     * чем PersistBeerDetailsStage/PersistProductImageStage добавляют
     * beerDetails/картинку (config/catalog_import.php:76,77,90) — у только
     * что импортированного товара первая seo-строка выйдет без картинки,
     * досоздать некому (BeerProductDetail — другая модель). Касается
     * только первого прохода нового товара при bulk-импорте; админка/
     * тесты/фабрики получают уже полностью загруженный $product.
     * Полноценный фикс — отдельный финальный stage в пайплайне, вне рамок
     * этой правки.
     */
    private const SEO_WATCHED_ATTRIBUTES = [
        'slug', 'name', 'in_stock',
        'volume_id', 'container_id', 'category_id', 'manufacturer_id',
    ];

    protected static function booted(): void
    {
        static::saved(function (Product $product): void {
            if ($product->wasRecentlyCreated || $product->wasChanged(self::SEO_WATCHED_ATTRIBUTES)) {
                app(SyncProductSeoAction::class)($product);
            }
        });

        static::deleted(fn (Product $product) => app(SyncProductSeoAction::class)->forget($product));
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
     * Сколько штук реально можно положить в корзину. in_stock и stock_quantity
     * могут разойтись (ручная правка в MoonShine, списание остатка при
     * оформлении заказа) — доступность считается по обоим полям сразу, и
     * только здесь; CartManager/CheckProductInStock/каталог/вёрстка кнопки
     * должны опираться на этот метод, а не читать поля по отдельности.
     */
    public function availableStock(): int
    {
        return $this->in_stock ? max(0, (int) $this->stock_quantity) : 0;
    }

    public function isAvailable(): bool
    {
        return $this->availableStock() > 0;
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

    /**
     * У товара есть собственная картинка. Untappd для части сортов
     * подставляет свой дефолтный бейдж (badge-beer-default-thumb) — он
     * такая же заглушка, как наша, поэтому тоже считается отсутствием
     * изображения. Используется каталогом, чтобы решить: показать <img>
     * или <x-ui.product-placeholder> (см. product-card.blade.php).
     */
    public function hasOwnImage(): bool
    {
        $url = $this->getFirstMedia('main')?->getUrl('thumb');

        return $url !== null && ! Str::contains($url, 'badge-beer-default-thumb');
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
                && $this->created_at->gt(now()->subDays(app(CatalogImportSettings::class)->new_days))
        );
    }
}
