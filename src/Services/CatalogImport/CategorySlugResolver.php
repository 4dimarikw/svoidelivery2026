<?php

namespace Services\CatalogImport;

use Services\CatalogImport\Dto\RawRow;

/**
 * Определяет slug категории из сырых CSV-колонок без обращения к БД.
 *
 * Тот же алгоритм, что и в ResolveCategoryStage, но работает с RawRow напрямую
 * и не создаёт/ищет Category-модель. Переиспользуется в NormalizeRowStage для
 * price-exemption до того, как ResolveCategoryStage запустит DB-lookup.
 */
final class CategorySlugResolver
{
    public function resolve(RawRow $row): string
    {
        $rawCategory = $row->get(config('catalog_import.columns.category'));
        $firstLevel = trim(explode('>', $rawCategory)[0]);
        $catLower = mb_strtolower($rawCategory);

        $alcoholMarker = config('catalog_import.alcohol_category_marker', 'Алкогольная продукция');
        $accessoryMarker = config('catalog_import.accessory_category_marker', 'Сопутствующие товары');
        $fallback = config('catalog_import.fallback_category', 'not-defined');

        // Branch 1: alcoholic products
        if ($firstLevel === $alcoholMarker) {
            return $this->resolveAlcoholicSlug($row, $catLower);
        }

        // Branch 2: probes (any non-alcoholic top segment containing the marker)
        $probesMarker = config('catalog_import.probes_marker', 'пробники');
        if ($firstLevel !== '' && str_contains($catLower, $probesMarker)) {
            return config('catalog_import.probes_category', 'probes');
        }

        // Branch 3: equipment (substring, case-insensitive) — ДО accessory,
        // т.к. реальный формат "Сопутствующие товары>Оборудование"
        $equipmentMarker = mb_strtolower(config('catalog_import.equipment_category_marker', 'Оборудование'));
        if (str_contains($catLower, $equipmentMarker)) {
            return config('catalog_import.equipment_category', 'equipment');
        }

        // Branch 4: accessories / companion goods
        if ($firstLevel === $accessoryMarker) {
            return $this->resolveAccessorySlug($row);
        }

        // Branch 5: catch-all
        return $fallback;
    }

    private function resolveAlcoholicSlug(RawRow $row, string $catLower): string
    {
        $adventMarker = config('catalog_import.advent_marker', 'адвент');
        $adventCategory = config('catalog_import.advent_category', 'souvenirs');
        $nonAlcoholicCategory = config('catalog_import.non_alcoholic_category', 'non-alcoholic');
        $defaultBeer = config('catalog_import.default_beer_category', 'beer');

        // a. Advent collections (special seasonal)
        if (str_contains($catLower, $adventMarker)) {
            return $adventCategory;
        }

        // b. No ABV → non-alcoholic beverage
        if (blank($row->get(config('catalog_import.columns.abv')))) {
            return $nonAlcoholicCategory;
        }

        // c. Style-based routing: mead / cider / sauce; default to beer
        $styleCategories = config('catalog_import.alcohol_style_categories', ['mead', 'cider', 'sauce']);
        $pattern = '/'.implode('|', array_map('preg_quote', $styleCategories)).'/i';
        $style = $row->get(config('catalog_import.columns.beer_style'));

        preg_match($pattern, mb_strtolower($style), $matches);

        return $matches[0] ?? $defaultBeer;
    }

    private function resolveAccessorySlug(RawRow $row): string
    {
        $titleMap = config('catalog_import.accessory_title_map', []);
        $fallback = config('catalog_import.fallback_category', 'not-defined');

        if (empty($titleMap)) {
            return $fallback;
        }

        $title = mb_strtolower($row->get(config('catalog_import.columns.name_full')));
        $pattern = '/'.implode('|', array_map('preg_quote', array_keys($titleMap))).'/i';

        preg_match($pattern, $title, $matches);

        if (isset($matches[0])) {
            return $titleMap[mb_strtolower($matches[0])] ?? $fallback;
        }

        return $fallback;
    }
}
