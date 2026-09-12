<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Product;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Support\VolumeText;
use Support\ValueObjects\Price;

/**
 * Создаёт товар или обновляет его по external_code. Требует brand, category и непустой код. Повторный импорт
 * обновляет price, stock_quantity, in_stock, volume_id, container_id, package_units, packaging_raw, name и
 * article; VolumeText удаляет упоминания объёма из текстов. Правки этих полей в админке могут быть перезаписаны
 * выгрузкой 1С. description, category_id, status, flags и brand задаются импортом только при создании и затем
 * редактируются в админке.
 */
final class PersistProductStage implements ImportStage
{
    public function __construct(private readonly CatalogImportSettings $settings) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->brand === null || $ctx->category === null) {
            $ctx->addWarning(self::class, 'missing brand or category, product skipped', '');
            $ctx->skip = true;
            $ctx->skipStage = self::class;

            return $ctx;
        }

        $attrs = $ctx->attributes;
        $externalCode = $attrs['external_code'] ?? '';

        if ($externalCode === '') {
            $ctx->addWarning(self::class, 'empty external_code (КодТовара), product skipped', '');
            $ctx->skip = true;
            $ctx->skipStage = self::class;

            return $ctx;
        }

        $stock = (int) ($attrs['stock'] ?? 0);

        [$packageUnits, $packagingRaw] = $this->parsePackaging($attrs['pack_info'] ?? null);

        $name = $attrs['title'] ?? $attrs['name'];
        $article = $attrs['article'] ?? null;

        $syncData = [
            'price' => Price::fromMajor($attrs['price'] ?? '')->decimal(),
            'stock_quantity' => $stock,
            'in_stock' => $stock > 0,
            'volume_id' => $ctx->volume?->id,
            'container_id' => $ctx->container?->id,
            'package_units' => $packageUnits,
            'packaging_raw' => $packagingRaw,
            'name' => $this->stripOrFallback($name),
            'article' => $article !== null ? $this->stripOrFallback($article) : null,
        ];

        $product = Product::query()->firstWhere('external_code', $externalCode);

        if ($product !== null) {
            $product->update($syncData);
        } else {
            $product = Product::create([
                'external_code' => $externalCode,
                'description' => $this->resolveDescription($ctx),
                'category_id' => $ctx->category->id,
                'manufacturer_id' => $ctx->brand->id,
                'source_category_path' => $attrs['category_path'] ?? null,
                'shelf_life_days' => $attrs['shelf_life_days'] ?? null,
                'brand' => $attrs['name'] ?? null,
                'status' => $this->settings->product_status,
                'flags' => $attrs['flags'] ?? [],
                ...$syncData,
            ]);
        }

        $ctx->product = $product;

        return $next($ctx);
    }

    /**
     * VolumeText::strip() убирает упоминание объёма из свободного текста
     * (Наименование/Артикул); если после вырезания ничего не осталось
     * (гипотетически — весь текст состоял только из объёма), используется
     * исходный текст как есть: products.name NOT NULL, пустое имя недопустимо.
     */
    private function stripOrFallback(string $text): string
    {
        $stripped = VolumeText::strip($text);

        return $stripped !== '' ? $stripped : $text;
    }

    /**
     * Untappd-описание в приоритете над `Описание` из CSV — одинаково на свежем
     * синке и на cache-hit, т.к. description персистится на untappd_beers.
     */
    private function resolveDescription(ImportContext $ctx): ?string
    {
        return $ctx->untappdBeer?->description ?? $ctx->attributes['description'] ?? null;
    }

    /**
     * Извлекает число упаковок из строки `Упаковка` (напр. "кор. 12х0,45л ж/б" → 12,
     * "Упаковка 6 шт." → 6). Эвристика, не найдено → null (package_units — nullable,
     * только для отображения, не участвует в дальнейшей логике импорта).
     *
     * @return array{int|null, string|null}
     */
    private function parsePackaging(?string $package): array
    {
        if ($package === null || $package === '') {
            return [null, null];
        }

        if (preg_match('/(\d+)\s*[xх×]/iu', $package, $m) || preg_match('/(\d+)\s*шт/iu', $package, $m)) {
            return [(int) $m[1], $package];
        }

        return [null, $package];
    }
}
