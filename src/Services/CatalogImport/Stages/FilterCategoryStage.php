<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Фильтрует строки по slug категории.
 *
 * Логика:
 * Если $ctx->categoryFilter пуст — фильтр выключен, все строки проходят.
 * Если slug категории ($ctx->category->slug) не входит в categoryFilter — строка
 * пропускается без warning (намеренная фильтрация, а не дефект данных).
 * Запускается после ResolveCategoryStage (category уже установлена).
 */
final class FilterCategoryStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->categoryFilter === []) {
            return $next($ctx);
        }

        if (! in_array($ctx->category?->slug, $ctx->categoryFilter, true)) {
            // Без addWarning() — намеренная фильтрация по --categories, не
            // дефект данных (см. докблок класса). CsvParserService бакетирует
            // такие строки отдельно от реальных потерь по $ctx->skipStage.
            $ctx->skip = true;
            $ctx->skipStage = self::class;

            return $ctx;
        }

        return $next($ctx);
    }
}
