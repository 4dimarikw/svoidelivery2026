<?php

declare(strict_types=1);

namespace Domain\Order\States;

use App\Events\Order\OrderStatusChanged;
use Domain\Order\Models\Order;
use InvalidArgumentException;

abstract class OrderState
{
    // Пусто по умолчанию — каждый наследник переопределяет своим набором
    // допустимых переходов (см. NewOrderState и т.д.). Раньше здесь стоял
    // OrderStatuses::class — enum, не state-класс, чего transitionTo()
    // ниже (сравнивает get_class($state)) никогда бы не нашла в списке.
    protected array $allowedTransitions = [];

    public function __construct(
        protected Order $order,
    )
    {
    }

    abstract public function canBeChanged(): bool;

    abstract public function value(): string;

    abstract public function humanValue(): string;

    public function transitionTo(OrderState $state): void
    {
        if (!$this->canBeChanged()) {
            throw new InvalidArgumentException('Status cannot be changed');
        }

        if (!in_array(get_class($state), $this->allowedTransitions)) {
            throw new InvalidArgumentException("No transition for {$this->order->status->value()} cannot be changed");
        }

        // Снимаем old ДО updateQuietly() — иначе $this->order->status ниже
        // читал бы уже новый статус (accessor пересчитывает его из свежего
        // атрибута), и old/current в событии совпадали бы.
        $old = $this->order->status;

        $this->order->updateQuietly([
            'status' => $state->value(),
        ]);

        event(
            new OrderStatusChanged(
                $this->order,
                $old,
                $state
            )
        );
    }

    public function getAllowedTransitions(): array
    {
        return $this->allowedTransitions;
    }
}
