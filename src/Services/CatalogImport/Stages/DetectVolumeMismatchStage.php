<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Support\VolumeText;

/**
 * Аудит расхождений объёма: сверяет объём, распознанный из Наименование/Товар
 * и отдельно из Артикул, с $ctx->volume (авторитетный источник, резолвится
 * ResolveVolumeStage из Упаковка). Ничего не резолвит и не пишет в products —
 * только фиксирует найденные расхождения в $ctx->attributes['volume_discrepancies']
 * для CsvParserService (см. ImportReport::$volumeDiscrepancies и
 * App\Events\CatalogVolumeDiscrepanciesDetected).
 *
 * Место в пайплайне — сразу после ResolveVolumeStage (нужен $ctx->volume);
 * Наименование/Товар/Артикул читаются напрямую из $ctx->row, поэтому стейдж
 * не зависит от порядка ResolveProductIdentityStage/ResolveExternalIdsStage.
 */
final class DetectVolumeMismatchStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->volume === null) {
            return $next($ctx);
        }

        $externalCode = $ctx->row->get(config('catalog_import.columns.product_code'));
        $packageMl = $ctx->volume->milliliters;
        $package = $ctx->row->get(config('catalog_import.columns.package'));

        $nameFull = $ctx->row->get(config('catalog_import.columns.name_full'))
            ?: $ctx->row->get(config('catalog_import.columns.product'));
        $article = $ctx->row->get(config('catalog_import.columns.article'));

        $this->compare($ctx, 'name', $nameFull, $externalCode, $package, $packageMl);
        $this->compare($ctx, 'article', $article, $externalCode, $package, $packageMl);

        return $next($ctx);
    }

    private function compare(ImportContext $ctx, string $source, string $text, string $externalCode, string $package, int $packageMl): void
    {
        if ($text === '') {
            return;
        }

        ['ml' => $textMl] = VolumeText::parse($text);

        if ($textMl === null || $textMl === $packageMl) {
            return;
        }

        $ctx->attributes['volume_discrepancies'][] = [
            'external_code' => $externalCode,
            'source' => $source,
            'package_raw' => $package,
            'package_ml' => $packageMl,
            'text_raw' => $text,
            'text_ml' => $textMl,
        ];
    }
}
