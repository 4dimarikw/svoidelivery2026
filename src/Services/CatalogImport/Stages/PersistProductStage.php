<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Product;
use Infrastructure\Settings\GeneralSettings;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохранение Product: создаёт или обновляет плоскую запись в таблице products
 * (эта схема не разделяет товар/вариацию — price/stock/volume/container живут
 * на самом товаре).
 *
 * Логика:
 * Требует $ctx->brand и $ctx->category (иначе skip); пустой external_code
 * (КодТовара) тоже skip — это ключ уникальности повторного импорта.
 * Ключ уникальности: [external_code] — updateOrCreate не дублирует товар.
 * `source_uuid` берётся из id_объекта (RawRow-UUID из 1С, уже провалидирован
 * NormalizeRowStage как непустой).
 */
final class PersistProductStage implements ImportStage
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->brand === null || $ctx->category === null) {
            $ctx->skip = true;

            return $ctx;
        }

        $attrs = $ctx->attributes;
        $externalCode = $attrs['external_code'] ?? '';

        if ($externalCode === '') {
            $ctx->addWarning(self::class, 'empty external_code (КодТовара), product skipped', '');
            $ctx->skip = true;

            return $ctx;
        }

        $stock = (int) ($attrs['stock'] ?? 0);
        [$packageUnits, $packagingRaw] = $this->parsePackaging($attrs['pack_info'] ?? null);

        $ctx->product = Product::updateOrCreate(
            ['external_code' => $externalCode],
            [
                'source_uuid' => $attrs['external_id'],
                'article' => $attrs['article'] ?? null,
                'name' => $attrs['title'] ?? $attrs['name'],
                'description' => $this->resolveDescription($ctx),
                'category_id' => $ctx->category->id,
                'manufacturer_id' => $ctx->brand->id,
                'volume_id' => $ctx->volume?->id,
                'container_id' => $ctx->container?->id,
                'price' => $attrs['price'] ?? 0,
                'stock_quantity' => $stock,
                'in_stock' => $stock > 0,
                'package_units' => $packageUnits,
                'packaging_raw' => $packagingRaw,
                'source_category_path' => $attrs['category_path'] ?? null,
                'shelf_life_days' => $attrs['shelf_life_days'] ?? null,
                'brand' => $attrs['name'] ?? null,
                'sales_rating' => $attrs['sales_rating'] ?? null,
                'status' => $this->settings->product_status,
                'synced_at' => now(),
            ],
        );

        return $next($ctx);
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
