<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Bootstraps the initial category list. The full registry (behavioural
 * import flags + slug-resolution rules) now lives in the `categories`/
 * `category_match_rules` tables and is editable in MoonShine — see
 * Services\CatalogImport\CategoryRegistry. This seeder only guarantees each
 * category row exists with its name/code; it never overwrites an existing
 * row, so admin edits made after the initial seed survive re-runs.
 *
 * `code` is derived from slug by replacing `-` with `_` (historical
 * convention of this project, see data/catalog-database-structure.md §4.1).
 */
class CategorySeeder extends Seeder
{
    private const array CATEGORIES = [
        'beer' => 'Пиво',
        'mead' => 'Мёд',
        'cider' => 'Сидр',
        'non-alcoholic' => 'Безалкогольные напитки',
        'sauce' => 'Соус',
        'not-defined' => 'не определена',
        'pet-tare-packages' => 'ПЭТ ТАРА ПАКЕТЫ',
        'for-beer' => 'К пиву',
        'clothes' => 'Одежда',
        'attributes' => 'Атрибутика',
        'souvenirs' => 'Сувениры',
        'probes' => 'Пробники',
        'souvenir-glasses' => 'Сувенирные бокалы',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => $name) {
            Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'code' => str_replace('-', '_', $slug),
                    'name' => $name,
                ],
            );
        }
    }
}
