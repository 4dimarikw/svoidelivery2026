<?php

namespace Services\CatalogImport\Support;

/**
 * Разбор и вырезание упоминаний объёма («число + "л"») из произвольного
 * текста 1С. Раньше жило приватным методом в ResolveVolumeStage — вынесено,
 * потому что тот же алгоритм разбора теперь нужен ещё и DetectVolumeMismatchStage
 * (сверка объёма в Наименование/Артикул с Упаковкой), а вырезание — только
 * что появившийся PersistProductStage::stripOrFallback().
 */
final class VolumeText
{
    /**
     * "0,45л", "20л/шт" и т.п. — сама цифра+"л", с приклеенным "/слово" суффиксом
     * (если он идёт вплотную, без пробела — то относится к объёму, а не к
     * посторонней части строки).
     */
    private const STRIP_PATTERN = '/\d+[,.]?\d*\s*л(\/\p{L}+)?\b/ui';

    /**
     * Ищет шаблон «число + "л"» в строке. При нескольких совпадениях
     * побеждает последнее (единица после "×", а не количество в коробке —
     * см. ResolveVolumeStage). Литры умножаются на 1000 и округляются до
     * целых миллилитров.
     *
     * @return array{ml: int|null, raw: string|null}
     */
    public static function parse(string $text): array
    {
        if (! preg_match_all('/(\d+[,\.]?\d*)\s*л/', $text, $matches)) {
            return ['ml' => null, 'raw' => null];
        }

        $values = $matches[1];
        $raw = end($values);
        $label = str_replace(',', '.', $raw).' л';
        $liters = (float) $label;
        $ml = (int) round($liters * 1000);

        return ['ml' => $ml > 0 ? $ml : null, 'raw' => $label];
    }

    /**
     * Вырезает упоминание объёма из свободного текста (Наименование/Артикул)
     * и подчищает осиротевшие разделители, оставшиеся после вырезания —
     * пустые скобки, висящие "/", задвоенные запятые/пробелы.
     *
     * Не трогает products.packaging_raw — та колонка обязана оставаться
     * вербатимной копией Упаковки.
     */
    public static function strip(string $text): string
    {
        $stripped = preg_replace(self::STRIP_PATTERN, '', $text) ?? $text;
        $stripped = preg_replace('/\(\s*\)/u', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+\)/u', ')', $stripped) ?? $stripped;
        $stripped = preg_replace('/\(\s*\//u', '(', $stripped) ?? $stripped;
        $stripped = preg_replace('/\/\s*\)/u', ')', $stripped) ?? $stripped;
        $stripped = preg_replace('/,\s*,/u', ',', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+\.(?=\s|$)/u', '.', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s{2,}/u', ' ', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s*,\s*$/u', '', $stripped) ?? $stripped;

        return trim($stripped, ' ,/');
    }
}
