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

        // Побочные эффекты после оформления (флеш статуса, выгрузка на FTP
        // 1С) живут в App\Listeners\Order\HandleOrderCreated, не здесь.
        event(new OrderCreated($order));

        return $order;
    }
}
