<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Volume;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Объём вариации: резолвит Volume из колонки Упаковка, заполняет $ctx->volume.
 *
 * Логика:
 * Регулярное выражение ищет шаблон «число + "л"» в строке Упаковка.
 * При нескольких совпадениях берётся последнее (единица после "×", а не количество в коробке).
 * Литры умножаются на 1000 и округляются до целых миллилитров.
 * Volume создаётся через firstOrCreate — объём является открытым множеством.
 * Примеры:
 *   "кор. 12х0,45л ж/б"      → 450 мл,   метка "0.45"
 *   "пэт кег 20л"             → 20000 мл, метка "20"
 *   "кор. 06х0,75л ст. бут."  → 750 мл,   метка "0.75"
 *   "кег кег 30л/кк"          → 30000 мл, метка "30"
 */
final class ResolveVolumeStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $package = $ctx->row->get(config('catalog_import.columns.package'));

        [$volumeMl, $label] = $this->parse($package);

        if ($volumeMl === null) {
            if ($this->categoryExpects($ctx)) {
                $ctx->addWarning(self::class, 'cannot parse volume', $package);
            }
        } else {
            $ctx->volume = Volume::firstOrCreate(
                ['value' => $volumeMl],
                ['title' => $label],
            );
        }

        return $next($ctx);
    }

    private function categoryExpects(ImportContext $ctx): bool
    {
        $slug = $ctx->category?->slug;

        return $slug !== null
            && in_array($slug, config('catalog_import.attribute_categories.volume', []), true);
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
            $liters = (float)$label;
            $volumeMl = (int)round($liters * 1000);

            return [$volumeMl > 0 ? $volumeMl : null, $label];
        }

        return [null, null];
    }
}
