<?php

namespace Tests\Feature\Logging;

use App\Events\UntappdBeerSyncFailed;
use Domain\Logging\Models\EventLog;
use Domain\Logging\Policies\EventLogPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Support\Logging\LogCorrelation;
use Tests\TestCase;

class SafeLoggingTest extends TestCase
{
    use RefreshDatabase;

    public static function channels(): array
    {
        return array_map(fn ($channel) => [$channel], ['single', 'daily', 'security', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog', 'stack']);
    }

    #[DataProvider('channels')]
    public function test_each_configured_channel_redacts_before_output(string $channel): void
    {
        // Создаём штатный канал, но заменяем только получателя: наружу ничего не отправляется.
        config(['logging.channels.slack.url' => 'https://example.test/fake', 'logging.channels.papertrail.handler_with' => ['host' => '127.0.0.1', 'port' => 514]]);
        Log::forgetChannel($channel);
        $logger = Log::channel($channel);
        $sink = new TestHandler;
        $logger->getLogger()->setHandlers([$sink]);
        $logger->error('FTP password=short-secret https://api.telegram.org/botroute-secret/getMe', [
            'init_data' => 'raw-init-data-secret', 'authorization' => 'Bearer header-secret',
            'exception' => new \RuntimeException('password=exception-secret'),
        ]);
        // Проверяем также прямое использование Monolog и его extra.
        $logger->getLogger()->error('token=direct-secret', ['initData' => 'direct-init-secret']);
        $records = $sink->getRecords();
        $serialized = json_encode(array_map(fn ($record) => $record->toArray(), $records));
        foreach (['short-secret', 'route-secret', 'raw-init-data-secret', 'header-secret', 'exception-secret', 'direct-secret', 'direct-init-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertCount(2, $records);
        $this->assertSame(LogCorrelation::id(), $records[0]->context['correlation_id']);
        $this->assertStringContainsString('RuntimeException', $serialized);
    }

    public function test_report_and_log_event_and_event_database_share_safe_correlation(): void
    {
        $sink = new TestHandler;
        Log::driver()->getLogger()->setHandlers([$sink]);
        $observed = [];
        Event::listen(MessageLogged::class, function ($event) use (&$observed) {
            $observed[] = $event;
        });
        report(new \RuntimeException('FTP failed password=short-secret'));
        event(new UntappdBeerSyncFailed(1, \RuntimeException::class, 'FTP failed password=short-secret'));
        $row = EventLog::query()->where('event_type', 'untappd_beer.sync_failed')->sole();
        $this->assertStringNotContainsString('short-secret', $row->message);
        $this->assertStringNotContainsString('short-secret', json_encode($row->context));
        $this->assertSame(LogCorrelation::id(), $row->context['correlation_id']);
        $this->assertTrue(Str::isUuid($row->context['correlation_id']));
        $this->assertNotEmpty($sink->getRecords());
        foreach ($observed as $event) {
            $this->assertStringNotContainsString('short-secret', $event->message);
            if (isset($event->context['exception'])) {
                $this->assertInstanceOf(\Throwable::class, $event->context['exception']);
                $this->assertStringNotContainsString('short-secret', (string) $event->context['exception']);
                $this->assertStringNotContainsString('short-secret', json_encode($event->context['exception']->getTrace()));
            }
        }
    }

    public function test_emergency_fallback_is_redacted_in_actual_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'safe-emergency-');
        config(['logging.channels.emergency.path' => $path, 'logging.channels.broken.driver' => 'missing-password=driver-secret']);
        try {
            Log::channel('broken')->error('password=emergency-secret');
            $text = file_get_contents($path);
            $this->assertStringNotContainsString('emergency-secret', $text);
            $this->assertStringNotContainsString('driver-secret', $text);
            $this->assertStringContainsString('correlation_id', $text);
        } finally {
            unlink($path);
        }
    }

    public function test_on_demand_file_channel_redacts_persisted_output(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'safe-log-');
        try {
            Log::build(['driver' => 'single', 'path' => $path])->warning('https://example.test/path?unusual=short-secret', ['password' => 'context-secret']);
            $text = file_get_contents($path);
            $this->assertStringNotContainsString('short-secret', $text);
            $this->assertStringNotContainsString('context-secret', $text);
            $this->assertStringContainsString('correlation_id', $text);
        } finally {
            unlink($path);
        }
    }

    public function test_event_log_requires_explicit_staff_permission(): void
    {
        $role = MoonshineUserRole::query()->create(['name' => 'Limited staff']);
        $user = MoonshineUser::query()->create([
            'name' => 'Staff', 'email' => 'limited-log-reader@example.test', 'password' => bcrypt('test-password'),
            'moonshine_user_role_id' => $role->id,
        ]);
        $policy = app(EventLogPolicy::class);
        $this->assertFalse($policy->viewAny($user, new EventLog));
        $this->assertFalse($policy->view($user, new EventLog));
    }
}
