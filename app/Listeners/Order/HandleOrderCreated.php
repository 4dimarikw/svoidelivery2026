<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCreated;
use Domain\Order\Actions\UploadOrderToFTP;

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
        // оформленный заказ.
        app(UploadOrderToFTP::class)->execute($event->order->id);
    }
}
