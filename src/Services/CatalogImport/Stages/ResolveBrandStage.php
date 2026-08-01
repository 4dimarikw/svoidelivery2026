<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Manufacturer;
use Services\CatalogImport\CategoryRegistry;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\UniqueSlugResolver;

/**
 * Бренд: резолвит производителя из колонки Производитель.
 *
 * Логика:
 * Читает колонку `Производитель`; пустое значение → default_brand категории
 * (categories.{slug}.default_brand), если задан. Для всех остальных случаев — warning + skip строки.
 * Ключуется по normalized_name (NOT NULL unique, моделью не генерируется) — не по name;
 * name и slug заполняются явно при создании.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class ResolveBrandStage implements ImportStage
{
    public function __construct(
        private readonly CategoryRegistry $categories = new CategoryRegistry,
        private readonly UniqueSlugResolver $slugs = new UniqueSlugResolver,
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

        $normalized = $this->normalize($name);

        $ctx->brand = Manufacturer::query()->firstOrCreate(
            ['normalized_name' => $normalized],
            ['name' => $name, 'slug' => $this->slugs->resolve(Manufacturer::class, $name)],
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

    private function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }
}
