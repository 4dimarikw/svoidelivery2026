<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Цена и остаток: парсит колонки Цена и Остаток.
 *
 * Логика:
 * `Цена`    → $attributes['price'] (float): убирает пробелы-разделители тысяч, запятую→точка.
 * `Остаток` → $attributes['stock'] (int).
 * Непарсируемые значения → 0 (не null), чтобы не нарушать NOT NULL ограничение схемы.
 */
final class ResolvePriceStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $priceRaw = $ctx->row->get(config('catalog_import.columns.price'));
        $stockRaw = $ctx->row->get(config('catalog_import.columns.stock'));

        $price = str_replace([',', ' '], ['.', ''], $priceRaw);

        if (! is_numeric($price)) {
            $ctx->addWarning(self::class, 'price not numeric, coerced to 0', $priceRaw);
            $ctx->priceCoerced = true;
        }

        if (! is_numeric($stockRaw)) {
            $ctx->addWarning(self::class, 'stock not numeric, coerced to 0', $stockRaw);
            $ctx->stockCoerced = true;
        }

        $ctx->attributes['price'] = is_numeric($price) ? (float) $price : 0.0;
        $ctx->attributes['stock'] = is_numeric($stockRaw) ? (int) $stockRaw : 0;

        return $next($ctx);
    }
}
