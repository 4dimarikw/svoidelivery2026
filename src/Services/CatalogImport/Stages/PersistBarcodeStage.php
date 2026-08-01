<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\ProductBarcode;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохранение штрихкода: создаёт запись в product_barcodes, если ещё не существует.
 *
 * Логика:
 * Читает колонку `ШтрихКод`; пропускает, если вариация не резолвлена или штрихкод пуст.
 * Excel сохраняет EAN-13 в научной нотации: "4,63E+12" → преобразуется в "4630000000000".
 * Ключ уникальности: [variation_id, barcode] → firstOrCreate не создаёт дубликатов.
 * Счётчик $ctx->barcodesCreatedThisRow увеличивается для итогового отчёта импорта.
 */
final class PersistBarcodeStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->variation === null) {
            return $next($ctx);
        }

        $raw = $ctx->row->get(config('catalog_import.columns.barcode'));
        $barcode = $this->parseBarcode($raw);

        if ($barcode !== null && $barcode !== '') {
            $record = ProductBarcode::firstOrCreate([
                'variation_id' => $ctx->variation->id,
                'barcode' => $barcode,
            ]);

            if ($record->wasRecentlyCreated) {
                $ctx->barcodesCreatedThisRow++;
            }
        }

        return $next($ctx);
    }

    /**
     * Handles scientific notation from Excel: "4,63E+12" → "4630000000000".
     */
    private function parseBarcode(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $raw);

        if (is_numeric($normalized)) {
            return sprintf('%.0f', (float) $normalized);
        }

        return $raw;
    }
}
