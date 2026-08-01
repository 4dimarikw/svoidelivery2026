<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Флаги статуса товара: читает колонку РейтингПродаж из 1С.
 *
 * Логика:
 * catalog_import.flags.featured_marker → $attributes['is_featured'] = true ("Акция")
 * Любое другое значение (в т.ч. пустое) → false.
 *
 * Флаг is_new вычисляется в PersistVariationStage по категории и возрасту вариации.
 */
final class ResolveFlagsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $flag = $ctx->row->get(config('catalog_import.columns.sales_rating'));

        $ctx->attributes['is_featured'] = $flag === config('catalog_import.flags.featured_marker', 'Акция');

        $ctx->attributes['metadata'] = config('catalog_import.variation_metadata');

        $ctx->attributes['metadata']['wu'] = $ctx->untappdBeer == null;

        $ctx->attributes['metadata']['mss'] = $ctx->category->slug == 'equipment';

        $ctx->attributes['metadata']['promo'] = $flag === config('catalog_import.flags.featured_marker', 'Акция');

        return $next($ctx);
    }
}
