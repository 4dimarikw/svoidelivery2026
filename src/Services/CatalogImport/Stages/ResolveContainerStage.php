<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\PackagingType;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Тип тары: определяет ContainerType для текущей вариации.
 *
 * Логика (3 шага):
 * 1. Категория НЕ в `attribute_categories.container` → stage пропускается целиком
 *    (fallback-тара `piece` проставляется в PersistVariationStage).
 * 2. Категория `pet-tare-packages` (`pet_tare_category`) → код всегда `pet`
 *    (значение `Упаковка` игнорируется).
 * 3. Иначе: определение **только по колонке `Упаковка`** (`columns.package`)
 *    через first-match-wins маппинг `container_map`.
 *    Нет совпадений → warning "unknown container type", тара не задаётся.
 *    Код найден, но запись не seeded → warning "container code not seeded".
 */
final class ResolveContainerStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        // Step 1 — categories outside attribute_categories.container are skipped.
        if (!$this->categoryExpects($ctx)) {
            return $next($ctx);
        }

        // Step 2 — pet-tare-packages always resolves to pet.
        if ($ctx->category?->slug === config('catalog_import.pet_tare_category', 'pet-tare-packages')) {
            $code = config('catalog_import.pet_tare_container_code', 'pet');
        } else {
            // Step 3 — detect from Упаковка column only (no Артикул fallback).
            $package = (string)$ctx->row->get(config('catalog_import.columns.package'));
            $code = $this->detectCode($package);

            if ($code === null) {
                $ctx->addWarning(self::class, 'unknown container type', $package);

                return $next($ctx);
            }
        }

        $ctx->containerType = $ctx->lookups?->findContainerByCode($code)
            ?? PackagingType::where('code', $code)->first();

        if ($ctx->containerType === null) {
            $ctx->addWarning(self::class, "container code '{$code}' not seeded", $code);
        }

        return $next($ctx);
    }

    private function categoryExpects(ImportContext $ctx): bool
    {
        $slug = $ctx->category?->slug;

        return $slug !== null
            && in_array($slug, config('catalog_import.attribute_categories.container', []), true);
    }

    private function detectCode(string $source): ?string
    {
        $map = config('catalog_import.container_map', []);

        foreach ($map as $needle => $code) {
            if (str_contains($source, $needle)) {
                return $code;
            }
        }

        return null;
    }
}
