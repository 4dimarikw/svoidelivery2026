<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderCustomer;

use App\MoonShine\Resources\OrderCustomer\Pages\OrderCustomerDetailPage;
use App\MoonShine\Resources\OrderCustomer\Pages\OrderCustomerFormPage;
use App\MoonShine\Resources\OrderCustomer\Pages\OrderCustomerIndexPage;
use Domain\Order\Models\OrderCustomer;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<OrderCustomer, OrderCustomerIndexPage, OrderCustomerFormPage, OrderCustomerDetailPage>
 */
class OrderCustomerResource extends ModelResource
{
    protected string $model = OrderCustomer::class;

    public function getTitle(): string
    {
        return __('moonshine.order_customer.title');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            OrderCustomerIndexPage::class,
            OrderCustomerFormPage::class,
            OrderCustomerDetailPage::class,
        ];
    }
}
