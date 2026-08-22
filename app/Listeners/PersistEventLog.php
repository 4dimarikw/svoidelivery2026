<?php

namespace App\Listeners;

use App\Events\LoggableEvent;
use Domain\Logging\Models\EventLog;
use Illuminate\Support\Str;
use Infrastructure\Settings\EventLoggingSettings;
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

        [$userId, $causerType] = $this->resolveCauser();

        try {
            EventLog::query()->create([
                'event_type' => $event->eventType(),
                'level' => $event->level(),
                'message' => $event->message(),
                'context' => $this->scrubContext($event->context()),
                'caused_by_user_id' => $userId,
                'caused_by_type' => $causerType,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private const array SENSITIVE_KEY_FRAGMENTS = [
        'password', 'passwd', 'token', 'secret', 'api_key', 'apikey',
        'authorization', 'bearer', 'cookie', 'session', 'csrf', 'private_key',
    ];

    // Long hex/base64 pattern may occasionally match hashes/UUIDs — intentional trade-off.
    private const array SENSITIVE_VALUE_PATTERNS = [
        '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
        '/\bey[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/',
        '/\b[A-Za-z0-9]{32,}\b/',
    ];

    /** @param array<string, mixed> $context */
    private function scrubContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (Str::contains(strtolower((string) $key), self::SENSITIVE_KEY_FRAGMENTS)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = $this->scrubContext($value);
            } elseif (is_string($value)) {
                $context[$key] = preg_replace(self::SENSITIVE_VALUE_PATTERNS, '[REDACTED]', $value) ?? $value;
            }
        }

        return $context;
    }

    /** @return array{int|null, string} */
    private function resolveCauser(): array
    {
        if (auth()->check()) {
            return [auth()->id(), 'user'];
        }

        if (app()->runningInConsole()) {
            return [null, 'console'];
        }

        return [null, 'system'];
    }
}
