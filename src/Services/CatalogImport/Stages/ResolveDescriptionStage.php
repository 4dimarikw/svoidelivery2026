<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Описание продукта: читает колонку Описание.
 *
 * Логика:
 * Непустое значение → $attributes['description'] (text).
 * Пустая строка → null.
 */
final class ResolveDescriptionStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.description'));
        $ctx->attributes['description'] = $raw !== '' ? $raw : null;

        return $next($ctx);
    }
}
