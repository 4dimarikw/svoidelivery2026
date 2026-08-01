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
 * Алгоритм (порядок ветвей важен):
 *
 * 1. Верхний сегмент `Категория` (до `>`) == `alcohol_category_marker`:
 *    a. `Категория` содержит `advent_marker` (без учёта регистра) → `advent_category`
 *    b. `ABV` пустой (blank) → `non_alcoholic_category`
 *    c. Иначе: preg_match(`/mead|cider|sauce/i`, `СтильПива`) → совпавший slug,
 *       без совпадения → `default_beer_category`
 *
 * 2. Верхний сегмент непустой AND `Категория` содержит `probes_marker` → `probes_category`
 *
 * 3. Верхний сегмент == `accessory_category_marker`:
 *    Regex по ключам `accessory_title_map` против mb_strtolower(`Наименование`)
 *    → map[match] ?? `fallback_category`
 *
 * 4. Иначе → `fallback_category`
 */
final readonly class ResolveCategoryStage implements ImportStage
{
    public function __construct(private CategorySlugResolver $resolver)
    {
    }

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $slug = $this->resolver->resolve($ctx->row);

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
