<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderNotificationEmailFailed;
use App\Events\Order\OrderTelegramNotificationFailed;
use Domain\Order\Actions\BuildOrderXml;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\Order;
use Domain\Telegram\Actions\SendTelegramMessage;
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
        $this->notifyAdminByTelegram($event->order);
    }

    /**
     * Письмо-уведомление админу о новом заказе. Сбой (SMTP недоступен,
     * ошибка рендера шаблона) не должен ронять уже оформленный заказ —
     * тот же принцип, что у UploadOrderToFTP::execute() (см. её
     * catch (Throwable)); попадает в event_logs через OrderNotificationEmailFailed.
     */
    private function notifyAdmin(Order $order): void
    {
        $emails = app(SiteSettings::class)->notifyEmails();

        if ($emails === []) {
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

            Mail::to($emails)->queue(new NewOrderCreated($order, $xml));
        } catch (Throwable $e) {
            report($e);
            event(new OrderNotificationEmailFailed(
                orderId: $order->id,
                orderNumber: $order->number,
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
            ));
        }
    }

    /**
     * Уведомление о новом заказе в служебную Telegram-группу
     * (config('services.telegram_notify')) — независимо от notifyAdmin()
     * (письмо): сбой одного канала не должен снимать другой. Сбой попадает
     * в event_logs через OrderTelegramNotificationFailed, тот же принцип,
     * что у notifyAdmin()/UploadOrderToFTP::execute() (см. их catch (Throwable)).
     */
    private function notifyAdminByTelegram(Order $order): void
    {
        $chatId = config('services.telegram_notify.manage_group');

        if ($chatId === null) {
            return;
        }

        try {
            // Дублирует loadMissing() из notifyAdmin() — безопасно (уже
            // загруженные связи не перезапрашиваются), а нужен на случай,
            // если email-ветка вообще не дошла до своего loadMissing()
            // (notifyEmails() === []).
            $order->loadMissing(['orderCustomer', 'orderItems.product', 'deliveryType']);

            app(SendTelegramMessage::class)(
                (string) $chatId,
                $this->buildTelegramMessage($order),
                config('services.telegram_notify.new_order_thread_id'),
            );
        } catch (Throwable $e) {
            report($e);
            event(new OrderTelegramNotificationFailed(
                orderId: $order->id,
                orderNumber: $order->number,
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
            ));
        }
    }

    /**
     * Простой Telegram HTML (parse_mode=HTML), без Blade-вьюхи — формат
     * сообщения мессенджера отличается от письма настолько, что общий
     * шаблон не даёт выгоды (тот же подход, что у
     * Domain\Vk\Actions\SendVkPostToChatAction::buildText()). Все
     * динамические значения экранированы через htmlspecialchars — Telegram
     * принимает узкое подмножество HTML, необработанные "<"/"&" ломают
     * разбор сообщения.
     */
    private function buildTelegramMessage(Order $order): string
    {
        $escape = fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $lines = [
            '🆕 <code>Новый заказ: '.$escape($order->number).'</code>',
            '<code>Сумма: '.$escape((string) $order->amount).'</code>',
            '',
            '<code>Получатель: </code>'.$escape(trim(($order->orderCustomer?->first_name ?? '').' '.($order->orderCustomer?->last_name ?? ''))),
            '<code>Телефон: </code>'.$escape($order->orderCustomer?->phone),
        ];

        if ($order->deliveryType?->with_address) {
            $lines[] = '<code>Город: </code>'.$escape($order->orderCustomer?->city);
            $lines[] = '<code>Адрес: </code>'.$escape($order->orderCustomer?->address);
        }

        $lines[] = '';
        $lines[] = '<code>Комментарий: </code> '.blank($order->comment) ? $escape($order->comment) : '<i>отсутствует</i>';

        $lines[] = '';
        $lines[] = "<blockquote expandable>Состав заказа\n";

        foreach ($order->orderItems as $item) {
            $name = $item->product?->brand ?: $item->product?->name;
            $lines[] = $escape($name).' × '.$item->quantity.' = '.$escape((string) $item->amount);
        }

        $lines[] = '</blockquote>';

        return implode("\n", $lines);
    }
}
