<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('vk_sync.download_images', false);
        $this->migrator->add('vk_sync.image_send_mode', 'original_url');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('vk_sync.download_images');
        $this->migrator->deleteIfExists('vk_sync.image_send_mode');
    }
};
