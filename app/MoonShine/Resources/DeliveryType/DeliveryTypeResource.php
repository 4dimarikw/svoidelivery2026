<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryType;

use App\MoonShine\Resources\DeliveryType\Pages\DeliveryTypeDetailPage;
use App\MoonShine\Resources\DeliveryType\Pages\DeliveryTypeFormPage;
use App\MoonShine\Resources\DeliveryType\Pages\DeliveryTypeIndexPage;
use Domain\Order\Models\DeliveryType;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<DeliveryType, DeliveryTypeIndexPage, DeliveryTypeFormPage, DeliveryTypeDetailPage>
 */
class DeliveryTypeResource extends ModelResource
{
    protected string $model = DeliveryType::class;

    public function getTitle(): string
    {
        return __('moonshine.delivery_type.title');
    }

    protected string $column = 'title';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            DeliveryTypeIndexPage::class,
            DeliveryTypeFormPage::class,
            DeliveryTypeDetailPage::class,
        ];
    }
}
