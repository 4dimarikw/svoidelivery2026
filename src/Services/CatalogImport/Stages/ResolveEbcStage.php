<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Цвет (EBC): парсит колонку EBC.
 *
 * Логика:
 * Числовое значение → $attributes['ebc'] (int).
 * Пусто или не число → null.
 */
final class ResolveEbcStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.ebc'));
        $ctx->attributes['ebc'] = $raw !== '' && is_numeric($raw) ? (int) $raw : null;

        return $next($ctx);
    }
}
