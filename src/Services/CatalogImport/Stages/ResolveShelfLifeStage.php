<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Срок годности: парсит колонку СрокГодности.
 *
 * Логика:
 * Числовое значение → $attributes['shelf_life_days'] (int, дни).
 * Пусто или не число → null.
 */
final class ResolveShelfLifeStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.shelf_life'));
        $ctx->attributes['shelf_life_days'] = $raw !== '' && is_numeric($raw) ? (int) $raw : null;

        return $next($ctx);
    }
}
