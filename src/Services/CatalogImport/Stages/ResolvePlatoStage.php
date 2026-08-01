<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Плотность (Plato): парсит колонку Plato.
 *
 * Логика:
 * Заменяет запятую на точку, парсит как float → $attributes['plato'].
 * Пусто или не число → null.
 */
final class ResolvePlatoStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.plato'));
        $normalized = str_replace(',', '.', $raw);
        $ctx->attributes['plato'] = $normalized !== '' && is_numeric($normalized) ? (float) $normalized : null;

        return $next($ctx);
    }
}
