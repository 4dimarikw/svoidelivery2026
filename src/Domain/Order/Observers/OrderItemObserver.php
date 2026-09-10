<?php

declare(strict_types=1);

namespace Domain\Order\Observers;

use Domain\Catalog\Actions\UpdateAmountOrder;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use LogicException;

class OrderItemObserver
{
    public function saving(OrderItem $item): void
    {
        if (! $item->exists || $item->isDirty('product_id')) {
            // Checkout передаёт уже заблокированную свежую модель.
            // При замене товара ранее загруженная связь больше не подходит.
            $product = ! $item->exists && $item->relationLoaded('product')
                ? $item->getRelation('product')
                : Product::query()->findOrFail($item->product_id);
            if ($product === null || (int) $product->id !== (int) $item->product_id) {
                throw new LogicException('Товар не соответствует позиции заказа.');
            }
            $item->product_name = $product->name;
            $item->product_external_code = $product->external_code;
            $item->product_brand = $product->brand;
            $item->setRelation('product', $product);
        } elseif ($item->isDirty(['product_name', 'product_external_code', 'product_brand'])) {
            throw new LogicException('Снимок товара нельзя менять без замены товара позиции.');
        }
    }

    public function created(OrderItem $orderItem): void
    {
        app(UpdateAmountOrder::class)->execute($orderItem->order);
    }

    public function updated(OrderItem $orderItem): void
    {
        if ($orderItem->wasChanged(['price', 'quantity', 'order_id'])) {
            $ids = array_unique([$orderItem->getRawOriginal('order_id'), $orderItem->order_id]);
            sort($ids, SORT_NUMERIC);
            foreach ($ids as $id) {
                app(UpdateAmountOrder::class)->execute(Order::query()->findOrFail($id));
            }
            $orderItem->unsetRelation('order');
        }
    }

    public function deleted(OrderItem $orderItem): void
    {
        app(UpdateAmountOrder::class)->execute($orderItem->order);
    }
}
