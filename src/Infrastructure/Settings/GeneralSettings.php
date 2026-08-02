<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public int $extra_charge;

    public ?string $test_user_email = null;

    public ?string $last_catalog_update = null;

    public ?string $notify_email = null;

    public int $untappd_update_limit = 0;

    public static function group(): string
    {
        return 'general';
    }
}
