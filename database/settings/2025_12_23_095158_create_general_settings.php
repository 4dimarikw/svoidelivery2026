<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('general.extra_charge', 0);
        $this->migrator->add('general.test_user_email', null);
        $this->migrator->add('general.notify_email', 'gdnwebm@yandex.ru');
        $this->migrator->add('general.last_catalog_update', null);
        $this->migrator->add('general.untappd_update_limit', 0);

        $this->migrator->add('general.product_status', 'published');

        $this->migrator->add('general.product_details', [
            ['name' => 'abv', 'label' => '%'],
            ['name' => 'ibu', 'label' => 'IBU'],
            ['name' => 'plato', 'label' => '°P'],
            ['name' => 'ebc', 'label' => 'EBC'],
        ]);

        $this->migrator->add('catalog.new_days', 7);

        $this->migrator->add('catalog.cache', false);
    }
};
