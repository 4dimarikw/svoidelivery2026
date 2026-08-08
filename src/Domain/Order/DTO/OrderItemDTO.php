<?php

declare(strict_types=1);

namespace Domain\Order\DTO;

use Domain\Cart\Models\CartItem;
use Support\Traits\Makeable;
use Support\ValueObjects\Price;

final class OrderItemDTO
{
    use Makeable;

    public function __construct(
        public readonly int $productId,
        public readonly Price $price,
        public readonly int $quantity,
    ) {}

    public static function fromArray(array $data): OrderItemDTO
    {
        return new self(
            productId: $data['product_id'],
            price: $data['price'],
            quantity: $data['quantity'],
        );
    }

    public static function fromCartItem(CartItem $item): OrderItemDTO
    {
        return new self(
            productId: $item->product_id,
            price: $item->price,
            quantity: $item->quantity,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            // PriceCast::set() принимает Price напрямую — не нужно вручную
            // разворачивать в рубли/копейки (ср. с прежним ->value(), не
            // существующим в реальном Price API).
            'price' => $this->price,
            'quantity' => $this->quantity,
        ];
    }
}
