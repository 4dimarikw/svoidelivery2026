<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Infrastructure\Settings\GeneralSettings;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Статус продаж: переносит сырую колонку РейтингПродаж в атрибуты товара.
 *
 * Логика:
 * `РейтингПродаж` → $attributes['sales_rating'] (products.sales_rating, строка как есть;
 * null если пусто). Схема этого проекта не хранит булевы флаги is_featured/is_new —
 * они были на ProductVariation в исходном проекте и не имеют аналога в плоской products.
 */
final class ResolveFlagsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $flag = $ctx->row->get(config('catalog_import.columns.sales_rating'));

        $ctx->attributes['sales_rating'] = $ctx->row->get(config('catalog_import.columns.sales_rating')) ?: null;

        $ctx->attributes['flags'] = app(GeneralSettings::class)->product_flags ?: [];

        $ctx->attributes['flags']['wu'] = $ctx->untappdBeer == null;

        $ctx->attributes['flags']['mss'] = false;

        $ctx->attributes['flags']['promo'] = $flag === config('catalog_import.flags.featured_marker', 'Акция');


        return $next($ctx);
    }
}
