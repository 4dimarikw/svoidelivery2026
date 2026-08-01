<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Категории строятся из `config('catalog_import.categories')` — единственного
 * реестра, которым также пользуются CategorySlugResolver и Resolve*-стейджи
 * импорта. Держать список категорий в двух местах (сидер + конфиг) означало
 * бы, что slug/code в БД неизбежно разойдётся с тем, что резолвит парсер.
 *
 * `code` выводится из slug заменой `-` на `_` (историческая договорённость
 * этого проекта, см. data/catalog-database-structure.md §4.1).
 */
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = config('catalog_import.categories', []);

        foreach ($categories as $slug => $config) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'code' => str_replace('-', '_', $slug),
                    'name' => $config['name'] ?? Str::headline($slug),
                    'sort_order' => $config['sort_order'] ?? 0,
                ],
            );
        }
    }
}
