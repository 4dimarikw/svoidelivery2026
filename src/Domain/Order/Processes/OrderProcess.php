<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Events\OrderCreated;
use Domain\Order\Models\Order;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

final class OrderProcess
{
    protected array $processes = [];

    public function __construct(
        protected Order $order
    ) {}

    public function processes(array $processes): self
    {
        $this->processes = $processes;

        return $this;
    }

    /**
     * Ошибка (в т.ч. Domain\Order\Exceptions\OrderProcessException из
     * TermsOrder/CheckProductInStock) не ловится здесь — DB::transaction()
     * сама откатывает всё уже сохранённое и пробрасывает исключение дальше,
     * контроллер решает, что показать пользователю (см. OrderController::store()).
     */
    public function run(): Order
    {
        $order = DB::transaction(fn (): Order => app(Pipeline::class)
            ->send($this->order)
            ->through($this->processes)
            ->thenReturn());

        // Тот же паттерн session('status'), что уже используют cart/address
        // страницы — не отдельные create_order_response/create_order_error
        // ключи, которых больше нигде в проекте нет.
        session()->flash('status', 'order-created');

        event(new OrderCreated($order));

        // После полного пайплайна, не на Order::created — на том шаге у
        // заказа ещё нет ни orderItems, ни orderCustomer (см. докблок
        // Domain\Order\Providers\OrderServiceProvider). UploadOrderToFTP
        // сама не бросает исключений наружу (см. её catch (Throwable)) —
        // сбой выгрузки не должен ронять уже оформленный заказ.
        app(UploadOrderToFTP::class)->execute($order->id);

        return $order;
    }
}
