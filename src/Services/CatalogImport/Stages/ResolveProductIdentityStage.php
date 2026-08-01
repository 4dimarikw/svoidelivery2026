<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\CategoryRegistry;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Имя продукта: заполняет name/brand из CSV.
 *
 * Логика:
 * `Марка`        → $attributes['name']  (краткое торговое название → products.brand).
 * `Наименование` → $attributes['title'] (полное название → products.name; резерв — колонка `Товар`).
 * Пустая `Марка` → для категорий с name_from_article (categories.{slug}.name_from_article)
 * имя берётся из `Артикул`; иначе warning + skip.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class ResolveProductIdentityStage implements ImportStage
{
    public function __construct(private readonly CategoryRegistry $categories = new CategoryRegistry) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $name = $ctx->row->get(config('catalog_import.columns.brand'));
        $nameFull = $ctx->row->get(config('catalog_import.columns.name_full'))
            ?: $ctx->row->get(config('catalog_import.columns.product'));

        if ($name === '') {
            $name = $this->articleFallbackName($ctx);

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
     * Для категорий с name_from_article и пустой Маркой имя берётся из Артикула, иначе ''.
     * Читает $ctx->category, установленный ResolveCategoryStage.
     */
    private function articleFallbackName(ImportContext $ctx): string
    {
        $slug = $ctx->category?->slug;

        return $slug !== null && $this->categories->nameFromArticle($slug)
            ? $ctx->row->get(config('catalog_import.columns.article'))
            : '';
    }
}
