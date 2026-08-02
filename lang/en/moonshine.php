<?php

// App-owned labels for our custom app/MoonShine/Resources/* classes (field
// labels, resource titles, menu group names). MoonShine's own admin::ui.*
// strings (moonshine_users, roles) stay in the package's own translations —
// this file only covers resources we authored.

return [
    'group' => [
        'catalog' => 'Catalog',
        'users' => 'Users',
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
            'flags' => 'Flags',
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

    'category' => [
        'title' => 'Categories',
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'slug' => 'Slug',
            'is_active' => 'Active',
            'products_count' => 'Products',
            'match_rules_count' => 'Rules',
            'expects_container' => 'Expects container',
            'expects_volume' => 'Expects volume',
            'price_exempt' => 'Skip min-price filter',
            'name_from_article' => 'Name from article',
            'default_brand' => 'Default brand',
            'container_code' => 'Fixed container',
            'match_rules' => 'Resolution rules',
            'properties' => 'Properties',
            'pivot_is_required' => 'Required',
            'pivot_is_filterable' => 'Filterable',
            'pivot_is_visible' => 'Visible',
            'pivot_sort_order' => 'Order',
        ],
        'tabs' => [
            'main' => 'Main',
            'import' => 'Import',
            'match_rules' => 'Resolution rules',
            'properties' => 'Properties',
        ],
    ],

    'category_match_rule' => [
        'title' => 'Category resolution rules',
        'fields' => [
            'type' => 'Type',
            'match_when' => 'When',
            'value' => 'Value',
            'priority' => 'Priority',
            'is_active' => 'Active',
        ],
    ],

    'catalog_import_settings' => [
        'title' => 'Import settings',
        'saved' => 'Settings saved',
        'fields' => [
            'alcohol_marker' => 'Alcohol marker',
            'accessory_marker' => 'Accessory marker',
            'advent_marker' => 'Advent marker',
            'fallback_slug' => 'Fallback category',
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

    'user' => [
        'title' => 'Users',
        'fields' => [
            'name' => 'Name',
            'email' => 'E-mail',
            'email_verified_at' => 'Email verified',
            'password' => 'Password',
            'password_confirmation' => 'Repeat password',
            'change_password' => 'Change password',
            'phone' => 'Phone',
            'created_at' => 'Registered',
            'addresses_count' => 'Addresses',
            'profile' => 'Profile',
            'addresses' => 'Addresses',
        ],
        'tabs' => [
            'main' => 'Main',
            'profile' => 'Profile',
            'addresses' => 'Addresses',
        ],
    ],

    'profile' => [
        'title' => 'Profiles',
        'fields' => [
            'full_name' => 'Full name',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'patronymic' => 'Patronymic',
            'phone' => 'Phone',
            'vk_url' => 'VK link',
            'telegram_url' => 'Telegram link',
            'default_order_comment' => 'Default order comment',
        ],
    ],

    'address' => [
        'title' => 'Addresses',
        'fields' => [
            'label' => 'Label',
            'city' => 'City',
            'street' => 'Street',
            'house' => 'House',
            'apartment' => 'Apartment',
            'entrance' => 'Entrance',
            'floor' => 'Floor',
            'intercom' => 'Intercom',
            'comment' => 'Comment',
            'is_default' => 'Default address',
        ],
    ],

    'untappd_beer' => [
        'title' => 'Untappd',
        'fields' => [
            'beer_id' => 'Untappd ID',
            'name' => 'Name',
            'brewery' => 'Brewery',
            'style' => 'Style',
            'description' => 'Description',
            'rating_count' => 'Ratings',
            'rating_score' => 'Rating',
            'label' => 'Image',
            'url' => 'URL',
            'synced_at' => 'Synced',
            'products_count' => 'Products',
        ],
        'actions' => [
            'resync' => 'Refresh from Untappd',
        ],
        'query_tags' => [
            'not_synced' => 'Not synced',
            'no_products' => 'No linked products',
        ],
        'toasts' => [
            'resync_success' => 'Data refreshed from Untappd',
            'resync_failed' => 'Failed to fetch data from Untappd',
        ],
    ],
];
