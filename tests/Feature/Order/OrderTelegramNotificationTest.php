<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Listeners\Order\HandleOrderCreated;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Telegraph as TelegraphClient;
use Domain\Logging\Models\EventLog;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\Order;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Settings\SiteSettings;
use Tests\TestCase;

/**
 * App\Listeners\Order\HandleOrderCreated::notifyAdminByTelegram() — часть,
 * отвечающая за уведомление в служебную Telegram-группу. По умолчанию в
 * тестовом окружении services.telegram_notify.manage_group не задан (нет
 * TELEGRAM_* в .env.testing) — существующие CheckoutTest/OrderNotificationEmailTest
 * этот код вообще не задевают, конфиг выставляется точечно здесь.
 */
class OrderTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ftp');
        Mail::fake();
    }

    public function test_nothing_is_sent_when_manage_group_is_not_configured(): void
    {
        Telegraph::fake();

        $order = Order::create([]);

        $this->callHandler($order);

        Telegraph::assertNothingSent();
        $this->assertSame(0, EventLog::query()->where('event_type', 'order.telegram_notification_failed')->count());
    }

    public function test_message_is_sent_to_manage_group_thread_on_success(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $order = Order::create([]);

        $this->callHandler($order);

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'chat_id' => '-100123456789',
            'message_thread_id' => 42,
        ], false);
    }

    /**
     * Сбой Telegram-уведомления не должен мешать письму — каналы
     * независимы (у каждого свой try/catch в HandleOrderCreated).
     */
    public function test_telegram_failure_is_logged_and_does_not_prevent_email(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake([
            TelegraphClient::ENDPOINT_MESSAGE => [
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ],
        ]);

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $order = Order::create([]);

        $this->callHandler($order);

        $event = EventLog::query()
            ->where('event_type', 'order.telegram_notification_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('error', $event->level);
        $this->assertSame($order->id, $event->context['order_id']);
        $this->assertSame($order->number, $event->context['order_number']);
        $this->assertSame('Bad Request: chat not found', $event->context['error_message']);
        $this->assertStringContainsString("Заказ №{$order->number}", $event->message);

        Mail::assertQueued(NewOrderCreated::class, fn ($mail) => $mail->order->id === $order->id);
    }

    private function callHandler(Order $order): void
    {
        app(HandleOrderCreated::class)->handle(new OrderCreated($order));
    }
}
