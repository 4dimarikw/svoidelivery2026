<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Manufacturer;

use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerFormPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerIndexPage;
use App\MoonShine\Support\GuardsRelatedDeletion;
use Domain\Catalog\Models\Manufacturer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<Manufacturer, ManufacturerIndexPage, ManufacturerFormPage, null>
 */
#[Icon('building-storefront')]
#[Group('Каталог', 'squares-2x2')]
#[Order(1)]
class ManufacturerResource extends ModelResource
{
    use GuardsRelatedDeletion;

    protected string $model = Manufacturer::class;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return 'Производители';
    }

    protected function pages(): array
    {
        return [
            ManufacturerIndexPage::class,
            ManufacturerFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'slug'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('products');
    }

    protected function deletionGuards(): array
    {
        return [
            'products' => 'Нельзя удалить производителя: он используется в товарах.',
        ];
    }
}
