<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Support\VolumeText;

/** Диагностика после сохранения: поля товара и решение о пропуске строки не изменяются. */
final class DetectFieldCollisionsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->skip || $ctx->product === null) {
            return $next($ctx);
        }

        $raw = [];
        foreach (['category', 'abv', 'name_full', 'product', 'article', 'brand'] as $field) {
            $column = config("catalog_import.columns.$field");
            $raw[$column] = $ctx->row->data[$column] ?? '';
        }
        $name = $ctx->row->get(config('catalog_import.columns.name_full'))
            ?: $ctx->row->get(config('catalog_import.columns.product'));
        $article = $ctx->row->get(config('catalog_import.columns.article'));
        $brand = $ctx->row->get(config('catalog_import.columns.brand'));

        // События модели могли прочитать характеристики до PersistBeerDetailsStage и закешировать null.
        // Обновляем только связи, не атрибуты/wasChanged, от которых зависят счётчики импорта.
        if ($ctx->product->exists) {
            $ctx->product->load(['category', 'beerDetails']);
        }
        $values = [
            'resolved' => ['category' => $ctx->category?->slug, 'abv' => $ctx->attributes['abv'] ?? null],
            'saved' => ['category' => $ctx->product->category?->slug, 'abv' => $ctx->product->beerDetails?->abv],
        ];
        $sources = ['resolved' => 'рассчитанные значения', 'saved' => 'сохранённые значения'];
        $packaging = str_contains(mb_strtolower($ctx->row->get(config('catalog_import.columns.category'))), 'подарочные короба')
            || preg_match('/^короб(?:ка)?\b/ui', $name) === 1;

        $matches = ['category_abv' => [], 'category_packaging' => []];
        foreach ($values as $source => $value) {
            if ($value['category'] !== 'non-alcoholic') {
                continue;
            }
            if ($value['abv'] !== null && (float) $value['abv'] > 0.5) {
                $matches['category_abv'][] = $source;
            }
            if ($packaging) {
                $matches['category_packaging'][] = $source;
            }
        }

        $messages = [
            'category_abv' => 'Категория «Безалкогольные напитки» при крепости выше 0,5%.',
            'category_packaging' => 'Категория «Безалкогольные напитки» при признаках подарочной упаковки.',
        ];
        foreach ($matches as $reason => $matched) {
            if ($matched !== []) {
                $this->record($ctx, $reason, $messages[$reason].' Проверка: '.implode(', ', array_map(fn ($s) => $sources[$s], $matched)).'.', $raw, $values, $matched);
            }
        }

        $brandWords = $this->words($brand);
        $candidates = array_filter([$this->words($name), $this->words($article)]);
        if ($brandWords !== [] && $candidates !== []) {
            foreach ($candidates as $words) {
                if (array_diff($brandWords, $words) === []) {
                    return $next($ctx);
                }
            }
            $this->record($ctx, 'name_mismatch', 'Возможное несоответствие — требуется проверка: «Марка» не согласуется с «Наименованием»/«Артикулом».', $raw, $values, ['csv']);
        }

        return $next($ctx);
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $text = str_replace('ё', 'е', mb_strtolower(VolumeText::strip($text)));

        return array_values(array_unique(preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY)));
    }

    private function record(ImportContext $ctx, string $reason, string $message, array $raw, array $values, array $sources): void
    {
        // Ключ причины исключает дублирование одной коллизии для рассчитанной и сохранённой категории.
        $ctx->fieldCollisions[$reason] = [
            'reason' => $reason,
            'message' => $message,
            'external_code' => $ctx->product->external_code,
            'product_id' => $ctx->product->getKey(),
            'fields' => $raw,
            'values' => $values,
            'sources' => $sources,
        ];
    }
}
