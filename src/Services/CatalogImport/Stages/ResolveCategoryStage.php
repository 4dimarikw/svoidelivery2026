<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Category;
use Services\CatalogImport\CategorySlugResolver;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Определяет категорию продукта из сырых колонок CSV до парсинга ABV.
 *
 * Резолвит slug через CategorySlugResolver (см. этот класс для точного
 * порядка ветвей: alcohol → contains → accessory_title → fallback, все
 * маркеры и правила читаются из CategoryRegistry, т.е. из БД), затем ищет
 * Category по slug. Категория НЕ создаётся автоматически — если slug не
 * найден в БД, строка помечается warning'ом и пропускается ($ctx->skip).
 */
final readonly class ResolveCategoryStage implements ImportStage
{
    public function __construct(private CategorySlugResolver $resolver) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $slug = $this->resolver->resolve($ctx->row);

        $ctx->attributes['category_path'] = $ctx->row->get(config('catalog_import.columns.category')) ?: null;

        $ctx->category = $ctx->lookups?->findCategoryBySlug($slug)
            ?? Category::query()->where('slug', $slug)->first();

        if ($ctx->category === null) {
            $ctx->addWarning(self::class, "category '{$slug}' not found in database", '');
            $ctx->skip = true;

            return $ctx;
        }

        return $next($ctx);
    }
}
