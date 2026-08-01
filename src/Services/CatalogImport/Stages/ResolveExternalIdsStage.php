<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Внешние идентификаторы: переносит коды 1С в атрибуты вариации.
 *
 * Логика:
 * `КодТовара`  → $attributes['sku']         (внутренний артикул 1С, ключ вариации).
 * `id_объекта` → $attributes['external_id'] (UUID объекта из 1С; null если пусто).
 * `Упаковка`   → $attributes['pack_info']   (сырая строка упаковки для хранения; null если пусто).
 */
final class ResolveExternalIdsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $ctx->attributes['sku'] = $ctx->row->get(config('catalog_import.columns.product_code'));
        $ctx->attributes['external_id'] = $ctx->row->get(config('catalog_import.columns.object_id')) ?: null;
        $ctx->attributes['pack_info'] = $ctx->row->get(config('catalog_import.columns.package')) ?: null;

        return $next($ctx);
    }
}
