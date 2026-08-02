<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('general.product_flags', [
            'wu' => false,
            'fil' => false,
            'mss' => false,
            'promo' => false,
        ]);
    }
};
