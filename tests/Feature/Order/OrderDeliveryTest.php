<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Jobs\DeliverOrderXmlJob;
use App\Jobs\UploadOrderToFtpJob;
use Domain\Order\Actions\CreateOrderDelivery;
use Domain\Order\Actions\DeliverOrderXml;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderDelivery;
use Domain\Order\Models\OrderItem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ftp');
        $this->freezeTime();
        $this->travelTo(now()->startOfSecond());
    }

    private function delivery(): OrderDelivery
    {
        $order = Order::create(['comment' => 'Первоначальный заказ']);
        OrderItem::factory()->for($order)->create(['quantity' => 2, 'price' => '100.00']);

        return app(CreateOrderDelivery::class)->initial($order->id);
    }

    public function test_repeat_uses_exact_bytes_and_new_version_uses_saved_changes(): void
    {
        $delivery = $this->delivery();
        $xml = $delivery->xml;
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->assertSame($xml, Storage::disk('ftp')->get($delivery->ftp_path));
        $order = $delivery->order;
        $item = $order->orderItems()->firstOrFail();
        $item->product->update(['external_code' => 'changed-code', 'name' => 'Новое имя']);
        $item->update(['quantity' => 3]);
        $order->update(['comment' => 'Исправленный заказ']);

        $this->assertTrue(app(CreateOrderDelivery::class)->retry($order->id, $delivery->id));
        $this->assertFalse(app(CreateOrderDelivery::class)->retry($order->id, $delivery->id));
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame($xml, Storage::disk('ftp')->get($delivery->ftp_path));
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertSame(1, $delivery->fresh()->cycle_attempts);

        $key = (string) Str::uuid();
        $next = app(CreateOrderDelivery::class)->newVersion($order->id, $key);
        $this->assertSame($next->id, app(CreateOrderDelivery::class)->newVersion($order->id, strtoupper($key))->id);
        $this->assertSame(2, $next->version);
        $this->assertStringEndsWith('_v2.xml', $next->ftp_path);
        $this->assertNotSame($xml, $next->xml);
        $this->assertStringContainsString('<Quantity>3</Quantity>', $next->xml);
        $this->assertStringNotContainsString('changed-code', $next->xml);
        app(DeliverOrderXml::class)->execute($next->id);
        $this->assertCount(2, Storage::disk('ftp')->allFiles());
    }

    public function test_rollback_leaves_no_delivery_and_initial_is_idempotent(): void
    {
        DB::beginTransaction();
        $delivery = $this->delivery();
        $this->assertSame($delivery->id, app(CreateOrderDelivery::class)->initial($delivery->order_id)->id);
        DB::rollBack();
        $this->assertSame(0, OrderDelivery::count());
        $this->assertSame(0, Order::count());
        $this->assertSame([], Storage::disk('ftp')->allFiles());
    }

    public function test_duplicate_worker_does_not_send_a_claimed_delivery(): void
    {
        $delivery = $this->delivery();
        $delivery->update(['state' => 'processing', 'attempts' => 1, 'cycle_attempts' => 1,
            'lock_token' => (string) Str::uuid(), 'locked_until' => now()->addSeconds(180)]);
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame([], Storage::disk('ftp')->allFiles());
        $this->travel(181)->seconds();
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->assertSame(2, $delivery->fresh()->attempts);
    }

    public function test_failure_backoff_exhaustion_and_manual_recovery(): void
    {
        config(['order.ftp_upload.disk' => 'missing']);
        $delivery = $this->delivery();
        foreach ([60, 120, 300, 600, null] as $index => $delay) {
            app(DeliverOrderXml::class)->execute($delivery->id);
            $delivery->refresh();
            $this->assertSame($index + 1, $delivery->attempts);
            $this->assertSame($delay === null ? 'failed' : 'retry', $delivery->state);
            if ($delay !== null) {
                $this->assertTrue($delivery->next_attempt_at->equalTo(now()->addSeconds($delay)));
                app(DeliverOrderXml::class)->execute($delivery->id);
                $this->assertSame($index + 1, $delivery->fresh()->attempts);
                $this->travel($delay)->seconds();
            }
        }
        Storage::fake('missing');
        app(CreateOrderDelivery::class)->retry($delivery->order_id, $delivery->id);
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->assertSame(6, $delivery->fresh()->attempts);
    }

    public function test_scheduler_recovers_lost_job_and_does_not_dispatch_sent_or_future_rows(): void
    {
        Queue::fake();
        $delivery = $this->delivery();
        $this->artisan('orders:dispatch-deliveries')->assertSuccessful();
        Queue::assertPushed(DeliverOrderXmlJob::class, fn ($job) => $job->deliveryId === $delivery->id);
        app(DeliverOrderXml::class)->execute($delivery->id);
        Queue::fake();
        $this->artisan('orders:dispatch-deliveries')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_old_job_creates_legacy_snapshot_once_without_sending_or_repeating_success(): void
    {
        $order = Order::create([]);
        (new UploadOrderToFtpJob($order->id))->handle();
        $delivery = $order->deliveries()->sole();
        $this->assertSame('legacy', $delivery->source);
        $this->assertSame([], Storage::disk('ftp')->allFiles());
        app(DeliverOrderXml::class)->execute($delivery->id);
        (new UploadOrderToFtpJob($order->id))->handle();
        $this->assertSame(1, $order->deliveries()->count());
        $this->assertSame('sent', $delivery->fresh()->state);
    }

    public function test_last_expired_attempt_is_failed_without_a_sixth_upload(): void
    {
        $delivery = $this->delivery();
        $delivery->update(['state' => 'processing', 'attempts' => 5, 'cycle_attempts' => 5,
            'lock_token' => (string) Str::uuid(), 'locked_until' => now()->subSecond()]);
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('failed', $delivery->fresh()->state);
        $this->assertSame(5, $delivery->fresh()->attempts);
        $this->assertSame([], Storage::disk('ftp')->allFiles());
    }

    public function test_disabled_delivery_preserves_pending_messages(): void
    {
        $delivery = $this->delivery();
        config(['order.ftp_upload.outbox_enabled' => false]);
        Queue::fake();
        $this->artisan('orders:dispatch-deliveries')->assertSuccessful();
        app(DeliverOrderXml::class)->execute($delivery->id);
        Queue::assertNothingPushed();
        $this->assertSame('pending', $delivery->fresh()->state);
        $this->assertSame([], Storage::disk('ftp')->allFiles());
    }

    public function test_stale_worker_cannot_complete_a_new_owners_attempt(): void
    {
        $delivery = $this->delivery();
        $newToken = (string) Str::uuid();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getAdapter')->andReturn(new \stdClass);
        $disk->shouldReceive('put')->once()->andReturnUsing(function () use ($delivery, $newToken): bool {
            // Другой worker уже восстановил просроченную блокировку.
            DB::table('order_deliveries')->where('id', $delivery->id)->update(['lock_token' => $newToken]);

            return true;
        });
        Storage::shouldReceive('disk')->with('ftp')->andReturn($disk);
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertSame('processing', $delivery->fresh()->state);
        $this->assertSame($newToken, $delivery->fresh()->lock_token);
        $this->assertNull($delivery->fresh()->sent_at);
    }

    public function test_transport_error_does_not_expose_xml_or_customer_data(): void
    {
        $delivery = $this->delivery();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getAdapter')->andReturn(new \stdClass);
        $disk->shouldReceive('put')->once()->andThrow(new \RuntimeException('sensitive-customer-secret '.$delivery->xml));
        Storage::shouldReceive('disk')->with('ftp')->andReturn($disk);
        app(DeliverOrderXml::class)->execute($delivery->id);
        $this->assertStringNotContainsString('sensitive-customer-secret', $delivery->fresh()->last_error);
        $logs = DB::table('event_logs')->where('event_type', 'order.ftp_upload_failed')->get()->toJson();
        $this->assertStringNotContainsString('sensitive-customer-secret', $logs);
        $this->assertStringNotContainsString('<Orders>', $logs);
    }
}
