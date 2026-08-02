<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Manufacturer;
use Services\CatalogImport\CategoryRegistry;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Бренд: резолвит производителя из колонки Производитель.
 *
 * Логика:
 * Читает колонку `Производитель`; пустое значение → default_brand категории
 * (categories.{slug}.default_brand), если задан. Для всех остальных случаев — warning + skip строки.
 * Ключуется по normalized_name (NOT NULL unique, моделью не генерируется) — не по name;
 * name заполняется явно при создании, slug генерируется Manufacturer::getSlugOptions()
 * (Spatie\Sluggable\HasSlug) через её собственный creating-listener.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class ResolveBrandStage implements ImportStage
{
    public function __construct(
        private readonly CategoryRegistry $categories = new CategoryRegistry,
    ) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $name = $ctx->row->get(config('catalog_import.columns.manufacturer'));

        if ($name === '') {
            $name = $this->defaultBrandName($ctx);

            if ($name === null) {
                $ctx->addWarning(self::class, 'empty brand', '');
                $ctx->skip = true;

                return $ctx;
            }
        }

        $normalized = normalize_name($name);

        $ctx->brand = Manufacturer::query()->firstOrCreate(
            ['normalized_name' => $normalized],
            ['name' => $name],
        );

        return $next($ctx);
    }

    /**
     * Для категории с default_brand и пустым Производитель возвращает дефолтный бренд, иначе null.
     * Читает $ctx->category, установленный ResolveCategoryStage.
     */
    private function defaultBrandName(ImportContext $ctx): ?string
    {
        $slug = $ctx->category?->slug;

        return $slug !== null ? $this->categories->defaultBrand($slug) : null;
    }
}
