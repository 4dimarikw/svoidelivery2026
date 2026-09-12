<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('event_logging.disabled_event_types', []);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('event_logging.disabled_event_types');
    }
};
