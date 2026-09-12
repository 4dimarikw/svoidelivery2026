<?php

namespace App\Listeners;

use App\Events\LoggableEvent;
use Domain\Auth\Models\User;
use Domain\Logging\Models\EventLog;
use Infrastructure\Settings\EventLoggingSettings;
use Support\Logging\LogCorrelation;
use Support\Logging\LogRedactor;
use Throwable;

class PersistEventLog
{
    public function __construct(
        private readonly EventLoggingSettings $settings,
    ) {}

    public function handle(LoggableEvent $event): void
    {
        // Управляется страницей "Система → Логирование событий"
        // (App\MoonShine\Pages\EventLoggingSettingsPage) — тип события,
        // выключенный там, вообще не попадает в event_logs.
        if ($this->settings->isDisabled($event->eventType())) {
            return;
        }

        [$userId, $causerType, $causerContext] = $this->resolveCauser();

        try {
            EventLog::query()->create([
                'event_type' => $event->eventType(),
                'level' => $event->level(),
                'message' => (new LogRedactor)->text($event->message()),
                'context' => [...(new LogRedactor)->value($event->context()), ...$causerContext, 'correlation_id' => LogCorrelation::id()],
                'caused_by_user_id' => $userId,
                'caused_by_type' => $causerType,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array{int|null, string, array<string, mixed>} */
    private function resolveCauser(): array
    {
        if (auth()->check()) {
            if (auth()->user() instanceof User) {
                return [auth()->id(), 'user', []];
            }

            // caused_by_user_id ссылается только на users, не на moonshine_users.
            return [null, 'admin', ['admin_id' => auth()->id()]];
        }

        if (app()->runningInConsole()) {
            return [null, 'console', []];
        }

        return [null, 'system', []];
    }
}
