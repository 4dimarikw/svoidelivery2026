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
    private const PROPERTIES = [
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
     */
    private const CATEGORY_PROPERTIES = [
        'beer' => ['volume', 'container', 'abv', 'ibu', 'plato', 'ebc', 'beer_style', 'untappd'],
        'mead' => ['volume', 'container', 'abv', 'beer_style', 'untappd'],
        'cider' => ['volume', 'container', 'abv', 'beer_style', 'untappd'],
        'soft_drinks' => ['volume', 'container'],
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
