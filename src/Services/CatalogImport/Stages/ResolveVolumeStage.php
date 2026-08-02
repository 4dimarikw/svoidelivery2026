<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Volume;
use Services\CatalogImport\CategoryRegistry;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Объём товара: резолвит Volume из колонки Упаковка, заполняет $ctx->volume.
 *
 * Логика:
 * Регулярное выражение ищет шаблон «число + "л"» в строке Упаковка.
 * При нескольких совпадениях берётся последнее (единица после "×", а не количество в коробке).
 * Литры умножаются на 1000 и округляются до целых миллилитров.
 * Volume создаётся через firstOrCreate по milliliters (unique) — объём является открытым множеством.
 * Примеры:
 *   "кор. 12х0,45л ж/б" → 450 мл,   метка "0.45"
 *   "пэт кег 20л" → 20000 мл, метка "20"
 *   "кор. 06х0,75л ст. бут."  → 750 мл,   метка "0.75"
 *   "кег кег 30л/кк" → 30000 мл, метка "30"
 */
final class ResolveVolumeStage implements ImportStage
{
    private readonly CategoryRegistry $categories;

    public function __construct(?CategoryRegistry $categories = null)
    {
        $this->categories = $categories ?? app(CategoryRegistry::class);
    }

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $package = $ctx->row->get(config('catalog_import.columns.package'));
        $product_code = $ctx->row->get(config('catalog_import.columns.product_code'));

        [$volumeMl, $label] = $this->parse($package);

        if ($volumeMl === null) {
            if ($this->categoryExpects($ctx)) {
                $ctx->addWarning(self::class, 'cannot parse volume', 'package_value='.$package.', product_code='.$product_code);
            }
        } else {
            $ctx->volume = Volume::firstOrCreate(
                ['milliliters' => $volumeMl],
                ['label' => $label],
            );
        }

        return $next($ctx);
    }

    private function categoryExpects(ImportContext $ctx): bool
    {
        $slug = $ctx->category?->slug;

        return $slug !== null && $this->categories->expectsVolume($slug);
    }

    /** @return array{int|null, string|null} */
    private function parse(string $package): array
    {
        // Pattern: digits (optional comma/dot digits) followed by "л"
        // Prefer the last match after "х12х0,45л" picks "0,45"
        if (preg_match_all('/(\d+[,\.]?\d*)\s*л/', $package, $matches)) {
            $values = $matches[1];
            $raw = end($values);
            $label = str_replace(',', '.', $raw);
            $liters = (float) $label;
            $volumeMl = (int) round($liters * 1000);

            return [$volumeMl > 0 ? $volumeMl : null, $label];
        }

        return [null, null];
    }
}
