<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Throwable;

/**
 * Загружает обложку товара с Untappd в медиа-коллекцию `main`.
 *
 * Логика:
 * Требует $ctx->product и $ctx->untappdBeer с непустым label.
 * Пропускает строки без untappd_beer или без label.
 * Не перезаписывает изображения, загруженные вручную (source != 'untappd').
 * Идемпотентен: повторный импорт с той же ссылкой не скачивает файл заново.
 * Ошибка скачивания → warning, импорт продолжается.
 *
 * Примечание: dry-run блокируется централизованно в CsvParserService::runPostCommitStages()
 * до вызова этого стейджа — отдельная проверка здесь не нужна.
 */
final class PersistProductImageStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $product = $ctx->product;
        $beer = $ctx->untappdBeer;

        if ($product === null || $beer === null || blank($beer->label)) {
            return $next($ctx);
        }

        $url = $beer->label;
        $collection = config('catalog_import.untappd_image.collection', 'main');
        $existing = $product->getFirstMedia($collection);

        if ($existing !== null && $existing->getCustomProperty('source') !== 'untappd') {
            return $next($ctx);
        }

        if ($existing !== null && $existing->getCustomProperty('source_url') === $url) {
            return $next($ctx);
        }

        try {
            $product->addMediaFromUrl($url)
                ->withCustomProperties(['source' => 'untappd', 'source_url' => $url])
                ->toMediaCollection($collection);

            $ctx->imageAttachedThisRow = true;
        } catch (Throwable $e) {
            $ctx->addWarning(self::class, 'image download failed: '.$e->getMessage(), $url);
        }

        return $next($ctx);
    }
}
