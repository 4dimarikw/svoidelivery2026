<?php

declare(strict_types=1);

namespace Domain\Order\States;

final class CompletedOrderState extends OrderState
{
    protected array $allowedTransitions = [];

    public function canBeChanged(): bool
    {
        return false;
    }

    public function value(): string
    {
        return 'completed';
    }

    public function humanValue(): string
    {
        return __('order.statuses.'.$this->value());
    }

    public function toString(): ?string
    {
        return $this->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }

    public function getColor(): string
    {
        return 'info';
    }
}
