<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Logging\Models\EventLog;
use Domain\Order\Actions\DeliverOrderXml;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Совместимость прежнего API и безопасная запись ошибок доставки в event_logs. */
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

        // Заказа нет — номер взять неоткуда, message() падает на #id.
        $this->assertNull($event->context['order_number']);
        $this->assertSame('Заказ #999999 не найден — выгрузка на FTP отменена.', $event->message);
    }

    public function test_legacy_call_persists_delivery_and_worker_records_failure(): void
    {
        Queue::fake();

        // Сохранение заявки не требует доступного транспорта; ошибка возникает в worker.
        config([
            'order.ftp_upload.disk' => 'nonexistent-disk',
        ]);

        $order = Order::create([]);

        $uploaded = app(UploadOrderToFTP::class)->execute($order->id);

        $this->assertTrue($uploaded);
        Queue::assertNothingPushed();
        $delivery = $order->deliveries()->sole();
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('retry', $delivery->fresh()->state);

        $event = EventLog::query()
            ->where('event_type', 'order.ftp_upload_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('warning', $event->level);
        $this->assertSame('upload_failed', $event->context['reason']);
        $this->assertTrue($event->context['queued']);

        // Worker использует сохранённую доставку, дополнительное чтение заказа не нужно.
        $this->assertSame($order->id, $event->context['order_id']);
        $this->assertNull($event->context['order_number']);
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
