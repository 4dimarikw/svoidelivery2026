<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Infrastructure\Settings\GeneralSettings;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Вычисляет флаги товара (products.flags, json) из колонки РейтингПродаж и
 * контекста импорта.
 *
 * Логика:
 * Стартует с GeneralSettings::$product_flags как seed, затем переопределяет:
 * `wu` — true, если товар не сматчен с Untappd ($ctx->untappdBeer === null);
 * `mss` — всегда false (нет источника данных для этого флага в текущем пайплайне);
 * `promo` — РейтингПродаж равен `catalog_import.flags.promo_marker` ('Акция').
 * `$ctx->attributes['flags']` читает PersistProductStage — только в create-ветке
 * (флаги вычисляются один раз при создании товара, повторный импорт их не
 * пересчитывает — как и остальные "статичные" атрибуты товара).
 */
final class ResolveFlagsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $flag = $ctx->row->get(config('catalog_import.columns.sales_rating'));

        $ctx->attributes['flags'] = app(GeneralSettings::class)->product_flags ?: [];

        $ctx->attributes['flags']['wu'] = $ctx->untappdBeer == null;

        $ctx->attributes['flags']['mss'] = false;

        $ctx->attributes['flags']['promo'] = $flag === config('catalog_import.flags.promo_marker', 'Акция');

        return $next($ctx);
    }
}
