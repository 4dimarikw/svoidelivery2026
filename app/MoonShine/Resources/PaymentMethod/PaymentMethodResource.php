<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\PaymentMethod;

use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodDetailPage;
use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodFormPage;
use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodIndexPage;
use Domain\Order\Models\PaymentMethod;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<PaymentMethod, PaymentMethodIndexPage, PaymentMethodFormPage, PaymentMethodDetailPage>
 */
#[Icon('credit-card')]
#[Group('moonshine.group.orders', 'shopping-cart', translatable: true)]
#[Order(4)]
class PaymentMethodResource extends ModelResource
{
    protected string $model = PaymentMethod::class;

    public function getTitle(): string
    {
        return __('moonshine.payment_method.title');
    }

    protected string $column = 'title';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            PaymentMethodIndexPage::class,
            PaymentMethodFormPage::class,
            PaymentMethodDetailPage::class,
        ];
    }
}
