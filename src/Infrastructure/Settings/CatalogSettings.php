<?php

namespace App\Settings;

use Domain\Product\Enums\ProductStatus;
use Spatie\LaravelSettings\Settings;

class CatalogSettings extends Settings
{
    public array $product_variation_metadata = [];

    public array $details_categories_slug = ['beer', 'cider', 'mead'];

    public array $liter_products_price = [];

    public array $product_details = [
        ['name' => 'abv', 'label' => '%'],
        ['name' => 'ibu', 'label' => 'IBU'],
        ['name' => 'plato', 'label' => '°P'],
        ['name' => 'ebc', 'label' => 'EBC'],
    ];

    public string $product_variation_status = ProductStatus::DRAFT->value;

    public int $new_days = 7;

    public bool $cache = false;

    public static function group(): string
    {
        return 'catalog';
    }
}
