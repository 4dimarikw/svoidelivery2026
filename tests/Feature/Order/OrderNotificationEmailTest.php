<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Listeners\Order\HandleOrderCreated;
use Domain\Logging\Models\EventLog;
use Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Settings\SiteSettings;
use RuntimeException;
use Tests\TestCase;

/**
 * App\Listeners\Order\HandleOrderCreated::notifyAdmin() — часть, отвечающая
 * за письмо-уведомление. Happy-path (письмо реально уходит в очередь) уже
 * покрыт CheckoutTest — здесь только два случая, которые он не проверяет.
 */
class OrderNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ftp');
    }

    public function test_no_email_is_queued_when_notify_email_is_not_set(): void
    {
        Mail::fake();

        $order = Order::create([]);

        $this->callHandler($order);

        Mail::assertNothingQueued();
    }

    public function test_mail_failure_is_logged_and_does_not_throw(): void
    {
        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $order = Order::create([]);

        // Mail::to(...)->queue(...) должен бросить — подменяем сам facade,
        // а не PendingMail (у него нет публичного конструктора для мока).
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP unreachable'));

        // Не должно долететь наружу — HandleOrderCreated::notifyAdmin()
        // ловит Throwable, ровно как UploadOrderToFTP::execute().
        $this->callHandler($order);

        $event = EventLog::query()
            ->where('event_type', 'order.notification_email_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('error', $event->level);
        $this->assertSame($order->id, $event->context['order_id']);
        $this->assertSame('SMTP unreachable', $event->context['error_message']);
    }

    /**
     * Листенер целиком (session()->flash + UploadOrderToFTP) дёргать не
     * нужно — тестируем только notifyAdmin(), но она private, поэтому
     * прогоняем через реальный handle() на изолированном Order без
     * связанных заказов/позиций (сама выгрузка на FTP тут не под тестом).
     */
    private function callHandler(Order $order): void
    {
        app(HandleOrderCreated::class)->handle(new OrderCreated($order));
    }
}
