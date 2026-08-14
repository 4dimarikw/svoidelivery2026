<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Product;
use Infrastructure\Settings\CatalogImportSettings;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Support\VolumeText;

/**
 * Сохранение Product: создаёт новую плоскую запись в таблице products
 * (эта схема не разделяет товар/вариацию — price/stock/volume/container живут
 * на самом товаре) либо частично обновляет уже существующую.
 *
 * Логика:
 * Требует $ctx->brand и $ctx->category (иначе skip); пустой external_code
 * (КодТовара) тоже skip — это ключ уникальности повторного импорта.
 * Ключ уникальности: [external_code].
 * Товар теперь редактируется из админки (ProductResource), поэтому повторный
 * импорт больше не переписывает карточку целиком — иначе правки admin'а
 * терялись бы на следующем catalog:import. Для уже существующего товара
 * обновляются оперативные поля ($syncData): price/stock_quantity/in_stock,
 * весь объёмный блок (volume_id/container_id/package_units/packaging_raw —
 * 1С иногда изначально присылает неверный объём тары и правит его в
 * последующих выгрузках), а также name/article — но БЕЗ упоминания объёма
 * (VolumeText::strip(), см. DetectVolumeMismatchStage, которая параллельно
 * фиксирует расхождение объёма между Упаковкой и Наименованием/Артикулом).
 * ВАЖНО: это значит, что правка name/article из админки может быть
 * переписана следующим catalog:import, если 1С прислал другой текст —
 * осознанный трейд-офф ради синхронизации объёма. description/category_id/
 * status/flags/brand по-прежнему трогает только create-ветка (первый
 * импорт) или сама админка.
 * `flags` берётся из $ctx->attributes['flags'] (см. ResolveFlagsStage) и,
 * как и остальные создаваемые-один-раз поля, не пересчитывается повторным
 * импортом — если нужно освежить flags у существующего товара, это делает
 * админка, а не catalog:import.
 */
final class PersistProductStage implements ImportStage
{
    public function __construct(private readonly CatalogImportSettings $settings) {}

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

        $name = $attrs['title'] ?? $attrs['name'];
        $article = $attrs['article'] ?? null;

        $syncData = [
            'price' => $attrs['price'] ?? 0,
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
