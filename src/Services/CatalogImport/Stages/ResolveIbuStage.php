<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * IBU (горечь): парсит колонку IBU.
 *
 * Логика:
 * Числовое значение → $attributes['ibu'] (int).
 * Пусто или не число → null.
 */
final class ResolveIbuStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.ibu'));
        $ctx->attributes['ibu'] = $raw !== '' && is_numeric($raw) ? (int) $raw : null;

        return $next($ctx);
    }
}
