<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderItem;

use App\MoonShine\Resources\OrderItem\Pages\OrderItemDetailPage;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemFormPage;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemIndexPage;
use Domain\Order\Models\OrderItem;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<OrderItem, OrderItemIndexPage, OrderItemFormPage, OrderItemDetailPage>
 */
class OrderItemResource extends ModelResource
{
    protected string $model = OrderItem::class;

    protected array $with = ['product', 'order'];

    public function getTitle(): string
    {
        return __('moonshine.order_item.title');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            OrderItemIndexPage::class,
            OrderItemFormPage::class,
            OrderItemDetailPage::class,
        ];
    }
}
