<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use Domain\Logging\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AlwaysFailingTestJob;
use Tests\TestCase;
use Throwable;

/**
 * App\Listeners\LogFailedQueueJob — общий предохранитель видимости для
 * любого упавшего job'а/Mailable в event_logs (см. её докблок и
 * App\Events\QueuedJobFailed). Queue::fake() тут не годится — она не
 * исполняет job и не шлёт Illuminate\Queue\Events\JobFailed, поэтому гоним
 * job по-настоящему под QUEUE_CONNECTION=sync (дефолт в phpunit.xml).
 * SyncQueue после отправки JobFailed перебрасывает исключение дальше —
 * ловим его сами.
 */
class LogFailedQueueJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_failure_is_logged_as_event(): void
    {
        try {
            AlwaysFailingTestJob::dispatch();

            $this->fail('Ожидался проброс исключения из-под sync-очереди.');
        } catch (Throwable) {
            // SyncQueue::handleException() перебрасывает исключение после
            // отправки JobFailed — сама задача теста не в этом, а в том,
            // что успело записаться в event_logs.
        }

        $event = EventLog::query()
            ->where('event_type', 'queue.job_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('error', $event->level);
        $this->assertSame(AlwaysFailingTestJob::class, $event->context['job_name']);
        $this->assertSame(1, $event->context['attempts']);
        $this->assertSame(\RuntimeException::class, $event->context['exception_class']);
        $this->assertSame('boom', $event->context['error_message']);
    }
}
