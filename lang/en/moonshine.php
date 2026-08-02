<?php

// App-owned labels for our custom app/MoonShine/Resources/* classes (field
// labels, resource titles, menu group names). MoonShine's own admin::ui.*
// strings (moonshine_users, roles) stay in the package's own translations —
// this file only covers resources we authored.

return [
    'group' => [
        'catalog' => 'Catalog',
    ],

    'product' => [
        'title' => 'Products',
        'fields' => [
            'image' => 'Image',
            'article' => 'Article',
            'name' => 'Name',
            'slug' => 'Slug',
            'category' => 'Category',
            'manufacturer' => 'Manufacturer',
            'volume' => 'Volume',
            'container' => 'Container',
            'price' => 'Price',
            'stock_quantity' => 'Stock',
            'in_stock' => 'In stock',
            'status' => 'Status',
            'external_code' => 'External code',
            'description' => 'Description',
            'brand' => 'Brand',
            'package_units' => 'Units per package',
            'packaging_raw' => 'Packaging (as in 1C)',
            'shelf_life_days' => 'Shelf life, days',
            'main_image' => 'Upload image',
            'current_image' => 'Current image',
            'beer_details' => 'Beer characteristics',
            'beer_style' => 'Style',
            'abv' => 'ABV, %',
            'ibu' => 'IBU',
            'plato' => 'Plato, °P',
            'ebc' => 'Color, EBC',
        ],
        'tabs' => [
            'main' => 'Main',
            'classification' => 'Classification',
            'price' => 'Price & stock',
            'extra' => 'Extra',
            'image' => 'Image',
            'beer' => 'Beer',
        ],
    ],

    'beer_style' => [
        'title' => 'Beer styles',
        'fields' => [
            'name' => 'Name',
            'parent' => 'Parent style',
            'slug' => 'Slug',
            'is_active' => 'Active',
            'products_count' => 'Products',
        ],
    ],

    'property' => [
        'title' => 'Properties',
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'unit' => 'Unit',
        ],
        'index' => [
            'type' => 'Type',
            'storage_table' => 'Table',
            'storage_column' => 'Column',
        ],
        'form' => [
            'data_type' => 'Data type',
            'storage_table' => 'Storage table',
            'storage_column' => 'Storage column',
        ],
    ],

    'container' => [
        'title' => 'Containers',
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'is_active' => 'Active',
            'products_count' => 'Products',
        ],
    ],

    'manufacturer' => [
        'title' => 'Manufacturers',
        'fields' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'is_active' => 'Active',
            'products_count' => 'Products',
        ],
    ],

    'volume' => [
        'title' => 'Volumes',
        'fields' => [
            'milliliters' => 'Volume, ml',
            'label' => 'Name',
            'products_count' => 'Products',
        ],
    ],
];
