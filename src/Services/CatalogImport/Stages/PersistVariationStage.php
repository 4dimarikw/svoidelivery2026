<?php

namespace Services\CatalogImport\Stages;

use Carbon\CarbonInterface;
use Closure;
use Domain\Catalog\Models\Category;
use Domain\Product\Enums\ProductStatus;
use Domain\Product\Models\ProductVariation;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохранение ProductVariation: создаёт или обновляет вариацию товара.
 *
 * Логика:
 * Требует $ctx->product и непустой sku (иначе skip с warning).
 * Ключ уникальности: ['sku'] — одна SKU не дублируется при повторном импорте.
 * Если ContainerType не резолвлен выше — container_type_id остаётся NULL.
 * Сохраняет объём, цену, остаток, pack_info; устанавливает is_active = true.
 *
 * is_new вычисляется по категории и возрасту вариации (catalog_import.new_flag):
 *   - категория не в new_flag.categories → false
 *   - новая вариация (создание) → true
 *   - существующая вариация: created_at > now - new_flag.days → true, иначе false
 * is_featured перезаписывается из $ctx->attributes при каждом импорте.
 */
final class PersistVariationStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->product === null) {
            $ctx->skip = true;

            return $ctx;
        }

        $sku = $ctx->attributes['sku'] ?? '';
        if ($sku === '') {
            $ctx->addWarning(self::class, 'empty SKU, variation skipped', '');
            $ctx->skip = true;

            return $ctx;
        }

        $attrs = $ctx->attributes;

        $variation = ProductVariation::firstOrNew(['sku_code' => $sku]);

        $status = match ($ctx->product->category->slug) {
            'equipment' => config('catalog_import.variation_status.equipment', ProductStatus::DRAFT),
            default => config('catalog_import.variation_status.default', ProductStatus::PUBLISHED),
        };

        if ($variation->exists) {
            $variation->fill([
                'price' => $attrs['price'] ?? 0,
                'stock_quantity' => $attrs['stock'] ?? 0,
                'is_new' => $this->resolveIsNew($ctx->category, $variation->created_at ?? null),
            ]);
        } else {
            $variation->fill([
                'product_id' => $ctx->product->id,
                'packaging_type_id' => $ctx->containerType?->id,
                'volume_id' => $ctx->volume?->id,
                'title' => $attrs['title'] ?? null,
                'price' => $attrs['price'] ?? 0,
                'stock_quantity' => $attrs['stock'] ?? 0,
                'pack' => $attrs['pack_info'] ?? null,
                'status' => $status,
                'metadata' => $attrs['metadata'] ?? [],
                'is_new' => true
            ]);
        }

        $variation->save();
        $ctx->variation = $variation;

        return $next($ctx);
    }

    private function resolveIsNew(?Category $category, ?CarbonInterface $createdAt): bool
    {
        $eligible = $category !== null
            && in_array($category->slug, config('catalog_import.new_flag.categories', []), true);

        if (!$eligible) {
            return false;
        }

        if ($createdAt === null) {
            return true; // новая вариация
        }

        $days = (int)config('catalog_import.new_flag.days', 7);

        return $createdAt->gt(now()->subDays($days));
    }
}
