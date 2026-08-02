<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Внешние идентификаторы: переносит коды 1С в атрибуты товара.
 *
 * Логика:
 * `КодТовара` → $attributes['external_code'] (ключ уникальности PersistProductStage).
 * `Артикул`   → $attributes['article']        (→ products.article, источник slug).
 * `Упаковка`  → $attributes['pack_info']      (сырая строка упаковки; null если пусто).
 */
final class ResolveExternalIdsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $ctx->attributes['external_code'] = $ctx->row->get(config('catalog_import.columns.product_code'));
        $ctx->attributes['article'] = $ctx->row->get(config('catalog_import.columns.article')) ?: null;
        $ctx->attributes['pack_info'] = $ctx->row->get(config('catalog_import.columns.package')) ?: null;

        return $next($ctx);
    }
}
