<?php

namespace Infrastructure\Settings;

use Domain\Catalog\Enums\ProductStatus;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public int $extra_charge;

    public ?string $test_user_email = null;

    public ?string $last_catalog_update = null;

    public ?string $notify_email = null;

    public int $untappd_update_limit = 0;

    public string $product_status = ProductStatus::DRAFT->value;

    public array $product_details = [
        ['name' => 'abv', 'label' => '%'],
        ['name' => 'ibu', 'label' => 'IBU'],
        ['name' => 'plato', 'label' => '°P'],
        ['name' => 'ebc', 'label' => 'EBC'],
    ];

    public int $new_days = 7;

    public bool $cache = false;

    public static function group(): string
    {
        return 'general';
    }
}
