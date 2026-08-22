<?php

namespace Infrastructure\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Управляет тем, какие типы App\Events\LoggableEvent реально попадают в
 * Domain\Logging\Models\EventLog — см. App\Listeners\PersistEventLog.
 * Список редактируемых типов — App\Events\Enums\LoggableEventType.
 */
class EventLoggingSettings extends Settings
{
    /** @var list<string> eventType()-строки, которые PersistEventLog не должен записывать. */
    public array $disabled_event_types = [];

    public static function group(): string
    {
        return 'event_logging';
    }

    public function isDisabled(string $eventType): bool
    {
        return in_array($eventType, $this->disabled_event_types, true);
    }
}
