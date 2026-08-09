<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * From data/catalog-database-structure.md §4.10. `storage_table`/
     * `storage_column` are metadata pointers for the application, not
     * MySQL foreign keys — they say where a filterable value actually lives.
     */
    private const array PROPERTIES = [
        'volume' => ['name' => 'Объём', 'data_type' => 'reference', 'unit' => 'мл', 'storage_table' => 'products', 'storage_column' => 'volume_id'],
        'container' => ['name' => 'Тара', 'data_type' => 'reference', 'unit' => null, 'storage_table' => 'products', 'storage_column' => 'container_id'],
        'abv' => ['name' => 'ABV', 'data_type' => 'decimal', 'unit' => '%', 'storage_table' => 'beer_product_details', 'storage_column' => 'abv'],
        'ibu' => ['name' => 'IBU', 'data_type' => 'decimal', 'unit' => 'IBU', 'storage_table' => 'beer_product_details', 'storage_column' => 'ibu'],
        'plato' => ['name' => 'Plato', 'data_type' => 'decimal', 'unit' => '°P', 'storage_table' => 'beer_product_details', 'storage_column' => 'plato'],
        'ebc' => ['name' => 'EBC', 'data_type' => 'decimal', 'unit' => 'EBC', 'storage_table' => 'beer_product_details', 'storage_column' => 'ebc'],
        'beer_style' => ['name' => 'Стиль пива', 'data_type' => 'reference', 'unit' => null, 'storage_table' => 'beer_product_details', 'storage_column' => 'beer_style_id'],
        'untappd' => ['name' => 'Untappd', 'data_type' => 'reference', 'unit' => null, 'storage_table' => 'beer_product_details', 'storage_column' => 'untappd_beer_id'],
    ];

    /**
     * Category → property codes to attach, per §4.11's proposed initial set.
     * `is_filterable` is true for everything here; the beer-only fields are
     * left off non-beer categories entirely rather than attached-but-hidden.
     *
     * Keys are `categories.code`, derived by CategorySeeder as
     * str_replace('-', '_', slug) — 'soft_drinks' here used to drift from
     * the actual code ('non-alcoholic' slug → 'non_alcoholic' code), so that
     * category silently got no properties; fixed to match reality.
     */
    private const array CATEGORY_PROPERTIES = [
        'beer' => ['volume', 'container', 'abv', 'ibu', 'plato', 'ebc', 'beer_style', 'untappd'],
        'mead' => ['volume', 'container', 'abv', 'beer_style', 'untappd'],
        'cider' => ['volume', 'container', 'abv', 'beer_style', 'untappd'],
        'non_alcoholic' => ['volume', 'container'],
        'sauce' => ['volume', 'container'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $properties = [];

        foreach (self::PROPERTIES as $code => $attributes) {
            $properties[$code] = Property::query()->updateOrCreate(
                ['code' => $code],
                $attributes,
            );
        }

        foreach (self::CATEGORY_PROPERTIES as $categoryCode => $propertyCodes) {
            $category = Category::query()->where('code', $categoryCode)->first();

            if (! $category) {
                continue;
            }

            // sync() would silently wipe admin-edited pivot values
            // (is_required/is_filterable/is_visible/sort_order) on every
            // re-run — only attach the initial set once, when the category
            // has no properties yet.
            if ($category->properties()->exists()) {
                continue;
            }

            $sync = [];

            foreach ($propertyCodes as $sortOrder => $propertyCode) {
                $sync[$properties[$propertyCode]->id] = [
                    'is_required' => false,
                    'is_filterable' => true,
                    'is_visible' => true,
                    'sort_order' => $sortOrder,
                ];
            }

            $category->properties()->sync($sync);
        }
    }
}
