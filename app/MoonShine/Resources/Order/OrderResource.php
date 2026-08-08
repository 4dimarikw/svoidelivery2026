<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order;

use App\MoonShine\Resources\Order\Pages\OrderDetailPage;
use App\MoonShine\Resources\Order\Pages\OrderFormPage;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use Domain\Order\Models\Order;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<Order, OrderIndexPage, OrderFormPage, OrderDetailPage>
 */
class OrderResource extends ModelResource
{
    protected string $model = Order::class;

    protected array $with = ['user', 'deliveryType', 'paymentMethod', 'orderCustomer', 'orderItems', 'orderItems.product'];

    public function getTitle(): string
    {
        return __('moonshine.order.title');
    }

    protected string $column = 'number';

    protected string $sortColumn = 'created_at';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            OrderIndexPage::class,
            OrderFormPage::class,
            OrderDetailPage::class,
        ];
    }

    public function search(): array
    {
        return ['id', 'number', 'comment', 'amount', 'user.name', 'orderCustomer.city', 'orderCustomer.street'];
    }
}
