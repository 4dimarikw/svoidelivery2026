<?php

declare(strict_types=1);

namespace Domain\Catalog\Actions;

use Dimarik\LaravelSeo\Models\Seo;
use Domain\Catalog\Models\Product;

/**
 * Синхронизирует SEO товара: title, description, keywords и text с OG и JSON-LD. Модель определяет момент
 * синхронизации через SEO_WATCHED_ATTRIBUTES, действие формирует текст. Пакет связывает SEO по URL, поэтому при
 * изменении slug действие обновляет или удаляет запись по прежнему адресу.
 */
final class SyncProductSeoAction
{
    /**
     * Дублирует ProductController::EAGER_LOAD — умышленно: обе точки хотят
     * одного и того же полностью собранного товара, но тащить приватную
     * константу контроллера в Action не стоит.
     */
    private const array RELATIONS = [
        'category', 'manufacturer', 'volume', 'container', 'media',
        'beerDetails.beerStyle', 'beerDetails.untappdBeer',
    ];

    private const ?string KEYWORDS_SUFFIX = null;

    /** FK-колонка => имя связи, которую она определяет — см. комментарий в __invoke(). */
    private const array FK_TO_RELATION = [
        'manufacturer_id' => 'manufacturer',
        'volume_id' => 'volume',
        'container_id' => 'container',
        'category_id' => 'category',
    ];

    public function __invoke(Product $product): void
    {
        // FK-поля из SEO_WATCHED_ATTRIBUTES (volume_id/container_id/
        // category_id/manufacturer_id) могли измениться прямо на этом
        // $product прямо перед вызовом (тот же объект — Product::booted()
        // передаёт саму сохранённую модель) — а loadMissing() пропускает
        // связь, если она уже когда-то была загружена, даже устаревшую
        // (например, если что-то читало $product->manufacturer до save()
        // с новым manufacturer_id). Сбрасываем только те связи, чей FK
        // реально изменился на этом save() — иначе catalog:sync-seo
        // (Product::query()->with(RELATIONS)->chunk(...), FK там не
        // меняются) на каждый товар заново бил бы по всем 7 связям вместо
        // переиспользования eager-load с уровня запроса чанка.
        foreach (self::FK_TO_RELATION as $column => $relation) {
            if ($product->wasChanged($column) && $product->relationLoaded($relation)) {
                $product->unsetRelation($relation);
            }
        }

        $product->loadMissing(self::RELATIONS);

        $oldSlug = $product->getOriginal('slug');

        if ($oldSlug !== null && $oldSlug !== $product->slug) {
            $this->forgetByRouteKey($product, $oldSlug);
        }

        Seo::query()->updateOrCreate(
            ['url' => $this->relativeUrl($product)],
            $this->fields($product),
        );
    }

    public function forget(Product $product): void
    {
        Seo::query()->where('url', $this->relativeUrl($product))->delete();
    }

    private function forgetByRouteKey(Product $product, string $oldSlug): void
    {
        $staleUrl = route(
            'product.show',
            $oldSlug.$product->getSlugOptions()->selfHealingSeparator.$product->getKey(),
            absolute: false,
        );

        Seo::query()->where('url', $staleUrl)->delete();
    }

    private function relativeUrl(Product $product): string
    {
        return route('product.show', $product, absolute: false);
    }

    /**
     * @return array{title: string, description: string, keywords: string, text: string}
     */
    private function fields(Product $product): array
    {
        $title = $product->name;
        $absoluteUrl = route('product.show', $product);

        $seoTitle = $this->buildTitle($product, $title);
        $seoDescription = $this->buildDescription($product, $title);

        return [
            'title' => $seoTitle,
            'description' => $seoDescription,
            'keywords' => $this->buildKeywords($product, $title),
            'text' => $this->buildText($product, $title, $seoTitle, $seoDescription, $absoluteUrl),
        ];
    }

    private function buildTitle(Product $product, string $title): string
    {
        $spec = collect([$product->volume?->label, $this->containerLabel($product)])
            ->filter()
            ->implode(' ');

        $manufacturer = $product->manufacturer?->name;

        $head = collect([
            $product->category?->name,
            $manufacturer ? "'{$manufacturer}'" : null,
            $title,
            $spec,
        ])->filter(fn (?string $part) => $part !== null && $part !== '')->implode(' ');

        return preg_replace('/\s+/', ' ', trim($head));
    }

    private function buildDescription(Product $product, string $title): string
    {
        $beer = $product->beerDetails;
        $manufacturer = $product->manufacturer?->name;

        $head = collect([$product->category?->name, $title])->filter()->implode(' ');

        $description = $head;

        $description .= '. '.$product->packaging_raw;

        return $description;
    }

    private function buildKeywords(Product $product, string $title): string
    {
        $manufacturer = $product->manufacturer?->name;
        $head = $manufacturer ? "{$manufacturer}, {$title}" : $title;

        return self::KEYWORDS_SUFFIX ? "{$head}, ".self::KEYWORDS_SUFFIX : $head;
    }

    private function buildText(
        Product $product,
        string $title,
        string $seoTitle,
        string $seoDescription,
        string $absoluteUrl,
    ): string {
        $manufacturer = $product->manufacturer?->name;
        $imageUrl = $product->hasOwnImage() ? $product->label : null;

        $og = [
            'og:type' => 'article',
            'og:title' => $seoTitle,
            'og:description' => $seoDescription,
            'og:url' => $absoluteUrl,
            'og:site_name' => config('app.name'),
        ];

        if ($imageUrl) {
            $og['og:image'] = $imageUrl;
            $og['og:image:alt'] = $manufacturer ? "{$manufacturer} ({$title})" : $title;
        }

        if ($product->created_at) {
            $og['article:published_time'] = $product->created_at->toJSON();
        }

        // Значения из 1С декодированы и могут содержать HTML-символы.
        // Поле text выводится как готовая разметка, поэтому экранируем атрибуты здесь.
        $ogHtml = collect($og)
            ->map(fn (string $content, string $property): string => sprintf(
                '<meta property="%s" content="%s">',
                $property,
                htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            ))
            ->implode("\n");

        $jsonLd = json_encode(
            $this->buildJsonLd($product, $title, $seoDescription, $absoluteUrl, $imageUrl),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
        );

        return $ogHtml."\n\n".'<script type="application/ld+json">'."\n".$jsonLd."\n".'</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonLd(
        Product $product,
        string $title,
        string $seoDescription,
        string $absoluteUrl,
        ?string $imageUrl,
    ): array {
        $manufacturer = $product->manufacturer?->name;

        $name = collect([$manufacturer, $title])->filter()->implode(' ');
        if ($product->volume?->label) {
            $name .= ' '.$product->volume->label;
        }
        if ($containerLabel = $this->containerLabel($product)) {
            $name .= ', '.$containerLabel;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $seoDescription,
            'brand' => ['@type' => 'Brand', 'name' => $title],
            'image' => $imageUrl,
            'offers' => [
                '@type' => 'Offer',
                'availability' => $product->in_stock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $absoluteUrl,
            ],
        ]);
    }

    private function containerLabel(Product $product): ?string
    {
        return $product->container?->label ?: $product->container?->name;
    }
}
