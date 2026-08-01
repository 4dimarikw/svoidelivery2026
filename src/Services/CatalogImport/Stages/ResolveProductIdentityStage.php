<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Имя продукта: заполняет name (product) и title (variation) из CSV.
 *
 * Логика:
 * `Марка`        → $attributes['name']  (краткое торговое название; ключ при поиске дубликата).
 * `Наименование` → $attributes['title'] (полное название вариации; резерв — колонка `Товар`).
 * Пустая `Марка` → для категории `equipment` имя берётся из `Артикул`; иначе warning + skip.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class ResolveProductIdentityStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $name = $ctx->row->get(config('catalog_import.columns.brand'));
        $nameFull = $ctx->row->get(config('catalog_import.columns.name_full'))
            ?: $ctx->row->get(config('catalog_import.columns.product'));

        if ($name === '') {
            $name = $this->equipmentFallbackName($ctx);

            if ($name === '') {
                $ctx->addWarning(self::class, 'empty product name (Марка)', '');
                $ctx->skip = true;

                return $ctx;
            }
        }

        $ctx->attributes['name'] = $name;
        $ctx->attributes['title'] = $nameFull ?: null;

        return $next($ctx);
    }

    /**
     * Для equipment с пустой Маркой имя берётся из Артикула, иначе ''.
     * Читает $ctx->category, установленный ResolveCategoryStage.
     */
    private function equipmentFallbackName(ImportContext $ctx): string
    {
        return $ctx->category?->slug === config('catalog_import.equipment_category', 'equipment')
            ? $ctx->row->get(config('catalog_import.columns.article'))
            : '';
    }
}
