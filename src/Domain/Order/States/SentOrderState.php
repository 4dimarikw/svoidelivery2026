<?php

declare(strict_types=1);

namespace Domain\Order\States;

final class SentOrderState extends OrderState
{
    protected array $allowedTransitions = [
        CompletedOrderState::class,
    ];

    public function canBeChanged(): bool
    {
        return true;
    }

    public function value(): string
    {
        return 'sent';
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
