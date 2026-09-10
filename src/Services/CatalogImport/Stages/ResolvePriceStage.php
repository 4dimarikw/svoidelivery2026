<?php

namespace Services\CatalogImport\Stages;

use Closure;
use InvalidArgumentException;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Support\ValueObjects\Price;

/**
 * Цена и остаток: парсит колонки Цена и Остаток.
 *
 * Логика:
 * `Цена`    → $attributes['price'] (точная десятичная строка через Price).
 * `Остаток` → $attributes['stock'] (int).
 * Ошибка цены пропускает строку до остальных стадий. Ошибка остатка, как прежде, даёт 0.
 */
final class ResolvePriceStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $priceRaw = $ctx->row->get(config('catalog_import.columns.price'));
        $stockRaw = $ctx->row->get(config('catalog_import.columns.stock'));

        try {
            $ctx->attributes['price'] = Price::fromMajor($priceRaw)->decimal();
        } catch (InvalidArgumentException) {
            $ctx->addWarning(self::class, 'invalid price, row skipped', $priceRaw);
            $ctx->skip = true;
            $ctx->skipStage = self::class;

            return $ctx;
        }

        if (! is_numeric($stockRaw)) {
            $ctx->addWarning(self::class, 'stock not numeric, coerced to 0', $stockRaw);
            $ctx->stockCoerced = true;
        }

        $ctx->attributes['stock'] = is_numeric($stockRaw) ? (int) $stockRaw : 0;

        return $next($ctx);
    }
}
