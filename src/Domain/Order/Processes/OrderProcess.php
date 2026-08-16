<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use App\Events\Order\OrderCreated;
use Domain\Order\Models\Order;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Throwable;

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
     *
     * @throws Throwable
     */
    public function run(): Order
    {
        $order = DB::transaction(fn (): Order => app(Pipeline::class)
            ->send($this->order)
            ->through($this->processes)
            ->thenReturn());

        // AssignProducts создаёт позиции через $order->orderItems()->createMany() —
        // OrderItemObserver пересчитывает и сохраняет amount, но на отдельном,
        // лениво подгруженном экземпляре Order (через $orderItem->order), а
        // не на этом $order. Без refresh() эмейл/Telegram-уведомление ниже
        // уходит с amount = 0, снятым ещё в PersistOrder до появления позиций.
        $order->refresh();

        event(new OrderCreated($order));

        return $order;
    }
}
