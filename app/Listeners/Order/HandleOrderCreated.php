<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderNotificationEmailFailed;
use Domain\Order\Actions\BuildOrderXml;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\Order;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Settings\SiteSettings;
use Throwable;

/**
 * Единственное место, которое владеет «что происходит после оформления
 * заказа» — тот же принцип, что у App\Listeners\CreateUserProfile для
 * Registered. Слушатель синхронный (не ShouldQueue) намеренно: он работает
 * с сессией текущего запроса, из очереди её бы не было.
 */
class HandleOrderCreated
{
    public function handle(OrderCreated $event): void
    {
        // Тот же паттерн session('status'), что уже используют cart/address
        // страницы — не отдельные create_order_response/create_order_error
        // ключи, которых больше нигде в проекте нет.
        session()->flash('status', 'order-created');

        // Событие диспатчится после полного пайплайна, не на Order::created —
        // на том шаге у заказа ещё нет ни orderItems, ни orderCustomer
        // (см. докблок Domain\Order\Providers\OrderServiceProvider).
        // UploadOrderToFTP сама не бросает исключений наружу (см. её
        // catch (Throwable)) — сбой выгрузки не должен ронять уже
        // оформленный заказ. Второй слой защиты здесь — на случай, если в
        // UploadOrderToFTP когда-нибудь появится необёрнутый путь (уже
        // бывало: до рефакторинга, добавившего её текущий catch(Throwable),
        // сбой resetFtpConnection() при отсутствующем league/flysystem-ftp
        // ронял весь запрос, и notifyAdmin() ниже не успевал отработать).
        try {
            app(UploadOrderToFTP::class)->execute($event->order->id);
        } catch (Throwable $e) {
            report($e);
        }

        $this->notifyAdmin($event->order);
    }

    /**
     * Письмо-уведомление админу о новом заказе. Сбой (SMTP недоступен,
     * ошибка рендера шаблона) не должен ронять уже оформленный заказ —
     * тот же принцип, что у UploadOrderToFTP::execute() (см. её
     * catch (Throwable)); попадает в event_logs через OrderNotificationEmailFailed.
     */
    private function notifyAdmin(Order $order): void
    {
        $email = app(SiteSettings::class)->notify_email;

        if ($email === null) {
            return;
        }

        try {
            // orderCustomer/orderItems.product/deliveryType используются и в
            // emails.new-order, и в BuildOrderXml — грузим явно, не по одному
            // лениво (та же причина, что у Order::with() в UploadOrderToFTP::execute()).
            $order->loadMissing(['orderCustomer', 'orderItems.product', 'deliveryType']);

            $xml = null;

            try {
                // Тот же XML, что уходит на FTP (UploadOrderToFTP), — вложением
                // в письмо, чтобы админ видел его и при незаметном сбое выгрузки.
                $xml = app(BuildOrderXml::class)->execute($order);
            } catch (Throwable $e) {
                // Сбой генерации XML не должен отменять само письмо — оно
                // ценнее вложения; вложения просто не будет.
                report($e);
            }

            Mail::to($email)->queue(new NewOrderCreated($order, $xml));
        } catch (Throwable $e) {
            report($e);
            event(new OrderNotificationEmailFailed(
                orderId: $order->id,
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
            ));
        }
    }
}
