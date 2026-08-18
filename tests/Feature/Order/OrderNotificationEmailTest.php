<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Listeners\Order\HandleOrderCreated;
use Domain\Logging\Models\EventLog;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Mail\NewOrderCreated;
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
        $this->assertSame($order->number, $event->context['order_number']);
        $this->assertSame('SMTP unreachable', $event->context['error_message']);
        $this->assertStringContainsString("Заказ №{$order->number}", $event->message);
    }

    /**
     * Регрессия: 15.08 в 07:48 сбой resetFtpConnection() (отсутствующий
     * league/flysystem-ftp) улетел необработанным из UploadOrderToFTP —
     * HandleOrderCreated::handle() до notifyAdmin() тогда не доходил.
     * Сегодняшний UploadOrderToFTP сам ловит Throwable, но HandleOrderCreated
     * теперь дублирует эту защиту на своём уровне — письмо должно уйти,
     * даже если UploadOrderToFTP когда-нибудь снова начнёт пробрасывать
     * исключения наружу.
     */
    public function test_ftp_upload_throwing_does_not_prevent_notification_email(): void
    {
        Mail::fake();

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $order = Order::create([]);

        $this->app->bind(UploadOrderToFTP::class, fn () => new class extends UploadOrderToFTP
        {
            public function execute(int $orderId, bool $queueOnFailure = true): bool
            {
                throw new RuntimeException('Class "League\Flysystem\Ftp\FtpAdapter" not found');
            }
        });

        $this->callHandler($order);

        Mail::assertQueued(NewOrderCreated::class, fn (NewOrderCreated $mail) => $mail->order->id === $order->id);
    }

    /**
     * XML заказа (BuildOrderXml, тот же, что уходит на FTP) должен быть
     * вложением письма — иначе почта не даёт ручного фолбэка на молчаливый
     * сбой выгрузки.
     */
    public function test_order_xml_is_attached_to_notification_email(): void
    {
        Mail::fake();

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $order = Order::create([]);

        $this->callHandler($order);

        Mail::assertQueued(NewOrderCreated::class, function (NewOrderCreated $mail) use ($order) {
            if ($mail->order->id !== $order->id || $mail->xml === null) {
                return false;
            }

            $attachments = $mail->attachments();

            return \count($attachments) === 1 && $attachments[0]->as === $mail->xml->filename;
        });
    }

    /**
     * notify_email хранит список адресов через ";" (SiteSettings::notifyEmails())
     * — письмо одно, но с несколькими получателями в To:.
     */
    public function test_multiple_addresses_are_all_queued_as_recipients(): void
    {
        Mail::fake();

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'a@example.test; b@example.test';
        $settings->save();

        $order = Order::create([]);

        $this->callHandler($order);

        Mail::assertQueued(NewOrderCreated::class, fn (NewOrderCreated $mail) => $mail->hasTo('a@example.test') && $mail->hasTo('b@example.test')
        );
    }

    /**
     * Мусор в списке (двойной ";", хвостовой ";") не должен ронять
     * уведомление целиком — notifyEmails() просто отбрасывает пустые части.
     */
    public function test_blank_entries_in_address_list_are_skipped(): void
    {
        Mail::fake();

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'a@example.test;;';
        $settings->save();

        $order = Order::create([]);

        $this->callHandler($order);

        Mail::assertQueued(NewOrderCreated::class, fn (NewOrderCreated $mail) => $mail->hasTo('a@example.test'));
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
