<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\PaymentMethod;

use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodDetailPage;
use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodFormPage;
use App\MoonShine\Resources\PaymentMethod\Pages\PaymentMethodIndexPage;
use Domain\Order\Models\PaymentMethod;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<PaymentMethod, PaymentMethodIndexPage, PaymentMethodFormPage, PaymentMethodDetailPage>
 */
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
