<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Fixed catalog category set from data/catalog-database-structure.md §4.1.
     * `unclassified` is mandatory — `products.category_id` is NOT NULL and
     * the future parser needs a fallback for anything it can't classify.
     */
    private const CATEGORIES = [
        'merchandise' => 'Атрибутика',
        'beer_related' => 'К пиву',
        'mead' => 'Мёд',
        'beer' => 'Пиво',
        'cider' => 'Сидр',
        'sauce' => 'Соус',
        'souvenirs' => 'Сувениры',
        'unclassified' => 'не определена',
        'soft_drinks' => 'Безалкогольные напитки',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $code => $name) {
            Category::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'slug' => Str::slug($code, '-')],
            );
        }
    }
}
