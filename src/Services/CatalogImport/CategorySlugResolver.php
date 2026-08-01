<?php

namespace Services\CatalogImport;

use Services\CatalogImport\Dto\RawRow;

/**
 * Определяет slug категории из сырых CSV-колонок без обращения к БД, используя
 * реестр `config('catalog_import.categories')` через CategoryRegistry.
 * Тот же алгоритм, что и в ResolveCategoryStage, но работает с RawRow напрямую
 * и не создаёт/ищет Category-модель. Переиспользуется в NormalizeRowStage для
 * price-exemption до того, как ResolveCategoryStage запустит DB-lookup.
 *
 * Порядок ветвей (см. шапку config/catalog_import.php):
 * 1. Верхний сегмент Категория == alcohol_marker → advent/no_abv/style/default.
 * 2. Категория (вся строка) содержит needle любой type=contains категории (порядок объявления).
 * 3. Верхний сегмент == accessory_marker → единый keyword-regex по всем type=accessory_title.
 * 4. Иначе → fallback.
 */
final class CategorySlugResolver
{
    public function __construct(private readonly CategoryRegistry $registry = new CategoryRegistry) {}

    public function resolve(RawRow $row): string
    {
        $rawCategory = $row->get(config('catalog_import.columns.category'));
        $firstLevel = trim(explode('>', $rawCategory)[0]);
        $catLower = mb_strtolower($rawCategory);

        // Branch 1: alcoholic products
        if ($firstLevel === $this->registry->alcoholMarker()) {
            return $this->resolveAlcoholicSlug($row, $catLower);
        }

        // Branch 2: type=contains — first declared category whose needle matches wins.
        foreach ($this->registry->rulesOfType('contains') as ['slug' => $slug, 'rule' => $rule]) {
            $needle = mb_strtolower((string) ($rule['needle'] ?? ''));
            if ($needle !== '' && str_contains($catLower, $needle)) {
                return $slug;
            }
        }

        // Branch 3: accessories / companion goods
        if ($firstLevel === $this->registry->accessoryMarker()) {
            return $this->resolveAccessoryTitleSlug($row);
        }

        // Branch 4: catch-all
        return $this->registry->fallback();
    }

    private function resolveAlcoholicSlug(RawRow $row, string $catLower): string
    {
        // a. Advent collections (special seasonal)
        if (str_contains($catLower, mb_strtolower($this->registry->adventMarker()))) {
            $slug = $this->slugForAlcoholWhen('advent');
            if ($slug !== null) {
                return $slug;
            }
        }

        // b. No ABV → non-alcoholic beverage
        if (blank($row->get(config('catalog_import.columns.abv')))) {
            $slug = $this->slugForAlcoholWhen('no_abv');
            if ($slug !== null) {
                return $slug;
            }
        }

        // c. Style-based routing (mead / cider / sauce / ...), declaration order wins
        $style = mb_strtolower($row->get(config('catalog_import.columns.beer_style')));

        foreach ($this->registry->rulesOfType('alcohol') as ['slug' => $slug, 'rule' => $rule]) {
            $keyword = mb_strtolower((string) ($rule['keyword'] ?? ''));
            if (($rule['when'] ?? null) === 'style' && $keyword !== '' && str_contains($style, $keyword)) {
                return $slug;
            }
        }

        return $this->slugForAlcoholWhen('default') ?? $this->registry->fallback();
    }

    private function slugForAlcoholWhen(string $when): ?string
    {
        foreach ($this->registry->rulesOfType('alcohol') as ['slug' => $slug, 'rule' => $rule]) {
            if (($rule['when'] ?? null) === $when) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Единый regex по keywords всех type=accessory_title категорий сразу —
     * леворасположенное совпадение побеждает (как в едином regex по всем keywords).
     */
    private function resolveAccessoryTitleSlug(RawRow $row): string
    {
        $fallback = $this->registry->fallback();

        $keywordToSlug = [];
        foreach ($this->registry->rulesOfType('accessory_title') as ['slug' => $slug, 'rule' => $rule]) {
            foreach ($rule['keywords'] ?? [] as $keyword) {
                $keywordToSlug[mb_strtolower($keyword)] = $slug;
            }
        }

        if ($keywordToSlug === []) {
            return $fallback;
        }

        $title = mb_strtolower($row->get(config('catalog_import.columns.name_full')));
        $pattern = '/'.implode('|', array_map('preg_quote', array_keys($keywordToSlug))).'/i';

        preg_match($pattern, $title, $matches);

        return isset($matches[0]) ? ($keywordToSlug[mb_strtolower($matches[0])] ?? $fallback) : $fallback;
    }
}
