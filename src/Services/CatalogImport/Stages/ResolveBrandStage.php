<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Vendor;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Бренд: резолвит производителя из колонки Производитель.
 *
 * Логика:
 * Читает колонку `Производитель`; пустое значение для категории `equipment` →
 * подставляет `catalog_import.equipment_default_brand` (`Рязанский Холод`).
 * Для всех остальных категорий — warning + skip строки.
 * Иначе вызывает Vendor::firstOrCreate(['name' => ...]) и сохраняет в $ctx->brand.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class ResolveBrandStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $name = $ctx->row->get(config('catalog_import.columns.manufacturer'));

        if ($name === '') {
            $name = $this->equipmentFallbackBrand($ctx);

            if ($name === null) {
                $ctx->addWarning(self::class, 'empty brand', '');
                $ctx->skip = true;

                return $ctx;
            }
        }

        $ctx->brand = Vendor::query()->firstOrCreate(['name' => $name]);

        return $next($ctx);
    }

    /**
     * Для equipment с пустым Производитель возвращает дефолтный бренд, иначе null.
     * Читает $ctx->category, установленный ResolveCategoryStage.
     */
    private function equipmentFallbackBrand(ImportContext $ctx): ?string
    {
        return $ctx->category?->slug === config('catalog_import.equipment_category', 'equipment')
            ? config('catalog_import.equipment_default_brand', 'Рязанский Холод')
            : null;
    }
}
