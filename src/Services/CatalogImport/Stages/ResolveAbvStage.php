<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * ABV (крепость): парсит колонку ABV с учётом Excel-мусора.
 *
 * Логика:
 * Excel с русской локалью превращает десятичные числа в даты:
 *   "4,2" → "04.фев"  (4 февраля → ABV = 4.2)
 *   "6,4" → "06.апр"  (6 апреля  → ABV = 6.4)
 *   "5,5" → "05.май"  (5 мая     → ABV = 5.5)
 * Декодирование: день + "." + номер_месяца → "4.2" → float 4.2.
 * Простые числа ("6", "0.5", "12") парсятся напрямую.
 * Резерв: regex по колонке Наименование ("алк. 4,2%").
 */
final class ResolveAbvStage implements ImportStage
{
    private const MONTHS_RU = [
        'янв' => 1,
        'фев' => 2,
        'мар' => 3,
        'апр' => 4,
        'май' => 5,
        'июн' => 6,
        'июл' => 7,
        'авг' => 8,
        'сен' => 9,
        'окт' => 10,
        'ноя' => 11,
        'дек' => 12,
    ];

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.abv'));

        $abv = $this->parseExcelDate($raw)
            ?? $this->parsePlainNumber($raw)
            ?? $this->parseFromName($ctx->row->get(config('catalog_import.columns.name_full')));

        if ($abv === null && $raw !== '') {
            $ctx->addWarning(self::class, 'unparsed ABV', $raw);
        }

        $ctx->attributes['abv'] = $abv;

        return $next($ctx);
    }

    private function parseExcelDate(string $raw): ?float
    {
        // Matches "04.фев", "06.апр", "05.май" etc.
        $pattern = '/^(\d{1,2})\.('.implode('|', array_keys(self::MONTHS_RU)).')$/u';

        if (preg_match($pattern, $raw, $m)) {
            $day = (int) $m[1];
            $month = self::MONTHS_RU[$m[2]];

            return (float) ($day.'.'.$month);
        }

        return null;
    }

    private function parsePlainNumber(string $raw): ?float
    {
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $raw);

        if (is_numeric($normalized)) {
            return (float) $normalized;
        }

        return null;
    }

    private function parseFromName(string $name): ?float
    {
        if (preg_match('/алк\.?\s*(\d+[,.]\d+)\s*%/u', $name, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }
}
