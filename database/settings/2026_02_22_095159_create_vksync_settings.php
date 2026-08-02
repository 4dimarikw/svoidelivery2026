<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('vk_sync.last_update', null);
        $this->migrator->add('vk_sync.domain', null);
        $this->migrator->add('vk_sync.count', 1);
        $this->migrator->add('vk_sync.cron', '1 * * * *');
        $this->migrator->add('vk_sync.active', false);
        $this->migrator->add('vk_sync.post_status', 'draft');
        $this->migrator->add('vk_sync.post_types', ['post']);
    }
};
