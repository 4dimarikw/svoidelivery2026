<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Illuminate\Support\Str;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Пред-обработка строки: пропускает пустые строки и артефакты заголовка,
 * строки без идентификаторов, с нулевой ценой и архивными категориями.
 *
 * Строка пропускается, если выполняется хотя бы одно условие:
 * — `id_объекта` пуст (пустая строка или строка заголовка);
 * — отсутствуют оба идентификатора `КодТовара` и `Артикул`;
 * — цена ниже catalog_import.normalize.min_price и категория не в price_exempt_categories;
 * — категория входит в catalog_import.normalize.excluded_categories.
 *
 * Читает $ctx->category (выставляется ResolveCategoryStage, идущим первым).
 */
final class NormalizeRowStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $reason = $this->exclusionReason($ctx);

        if ($reason !== null) {
            $ctx->addWarning(self::class, "row excluded: $reason", $ctx->row->get(config('catalog_import.columns.object_id')));
            $ctx->skip = true;

            return $ctx;
        }

        return $next($ctx);
    }

    private function exclusionReason(ImportContext $ctx): ?string
    {
        return match (true) {
            $this->lacksObjectId($ctx) => 'пустой id_объекта',
            $this->lacksIdentifiers($ctx) => 'нет КодТовара или Артикул',
            $this->priceBelowMinimum($ctx) && !$this->isPriceExempt($ctx) => 'цена ниже минимума',
            $this->isExcludedCategory($ctx) => 'исключённая категория',
            default => null,
        };
    }

    /**
     * Возвращает true если категория (slug) входит в price_exempt_categories.
     * Читает $ctx->category, установленный ResolveCategoryStage (идёт до Normalize).
     */
    private function isPriceExempt(ImportContext $ctx): bool
    {
        $exempt = config('catalog_import.normalize.price_exempt_categories', []);

        return $exempt !== [] && in_array($ctx->category?->slug, $exempt, true);
    }

    /**
     * Пустой `id_объекта` — строка-заголовок или пустая строка.
     */
    private function lacksObjectId(ImportContext $ctx): bool
    {
        return $ctx->row->get(config('catalog_import.columns.object_id')) === '';
    }

    /**
     * Отсутствуют идентификаторы: КодТовара или Артикул.
     */
    private function lacksIdentifiers(ImportContext $ctx): bool
    {
        return blank($ctx->row->get(config('catalog_import.columns.product_code')))
            || blank($ctx->row->get(config('catalog_import.columns.article')));
    }

    /**
     * Цена ниже допустимого минимума (catalog_import.normalize.min_price).
     * Нормализация совпадает с ResolvePriceStage: убираем пробелы-разделители и запятую→точка.
     */
    private function priceBelowMinimum(ImportContext $ctx): bool
    {
        $raw = str_replace([',', ' '], ['.', ''], $ctx->row->get(config('catalog_import.columns.price')));
        $price = is_numeric($raw) ? (float)$raw : 0.0;

        return $price < (int)config('catalog_import.normalize.min_price', 2);
    }

    /**
     * Категория товара входит в список исключённых (catalog_import.normalize.excluded_categories).
     */
    private function isExcludedCategory(ImportContext $ctx): bool
    {
        return Str::contains(
            $ctx->row->get(config('catalog_import.columns.category')),
            config('catalog_import.normalize.excluded_categories', []),
        );
    }
}
