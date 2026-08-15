<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Jobs\UploadOrderToFtpJob;
use Domain\Logging\Models\EventLog;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Domain\Order\Actions\UploadOrderToFTP не имела собственного покрытия —
 * тест заодно проверяет перевод сбоев с Log на EventLog (event_logs,
 * см. App\Listeners\PersistEventLog).
 */
class UploadOrderToFtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_order_logs_order_not_found_event(): void
    {
        $uploaded = app(UploadOrderToFTP::class)->execute(999999);

        $this->assertFalse($uploaded);

        $event = EventLog::query()
            ->where('event_type', 'order.ftp_upload_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('error', $event->level);
        $this->assertSame('order_not_found', $event->context['reason']);
        $this->assertSame(999999, $event->context['order_id']);
        $this->assertFalse($event->context['queued']);
    }

    public function test_upload_failure_logs_event_and_queues_job(): void
    {
        Queue::fake();

        // Несуществующий диск роняет Storage::disk()->put() уже на первой
        // попытке (max_attempts принудительно занижен до 1, чтобы не ждать
        // реальных ретраев в тесте).
        config([
            'order.ftp_upload.disk' => 'nonexistent-disk',
            'order.ftp_upload.max_attempts' => 1,
        ]);

        $order = Order::create([]);

        $uploaded = app(UploadOrderToFTP::class)->execute($order->id);

        $this->assertFalse($uploaded);

        Queue::assertPushed(UploadOrderToFtpJob::class, fn (UploadOrderToFtpJob $job) => $job->orderId === $order->id);

        $event = EventLog::query()
            ->where('event_type', 'order.ftp_upload_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('warning', $event->level);
        $this->assertSame('upload_failed', $event->context['reason']);
        $this->assertTrue($event->context['queued']);
    }

    public function test_successful_upload_does_not_write_event_log(): void
    {
        Storage::fake('ftp');

        $order = Order::create([]);

        $uploaded = app(UploadOrderToFTP::class)->execute($order->id);

        $this->assertTrue($uploaded);
        $this->assertSame(0, EventLog::query()->where('event_type', 'order.ftp_upload_failed')->count());
    }
}
