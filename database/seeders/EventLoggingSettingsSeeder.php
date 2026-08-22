<?php

namespace Database\Seeders;

use Infrastructure\Settings\EventLoggingSettings;

/**
 * Дефолты 1:1 с database/settings/2026_08_22_170500_create_event_logging_settings.php.
 */
class EventLoggingSettingsSeeder extends AbstractSettingsSeeder
{
    protected function settingsClass(): string
    {
        return EventLoggingSettings::class;
    }

    protected function defaults(): array
    {
        return [
            'disabled_event_types' => [],
        ];
    }
}
