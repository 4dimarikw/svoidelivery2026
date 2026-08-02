<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Volume;

use App\MoonShine\Resources\Volume\Pages\VolumeFormPage;
use App\MoonShine\Resources\Volume\Pages\VolumeIndexPage;
use App\MoonShine\Support\GuardsRelatedDeletion;
use Domain\Catalog\Models\Volume;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Volume, VolumeIndexPage, VolumeFormPage, null>
 */
#[Icon('beaker')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(3)]
class VolumeResource extends ModelResource
{
    use GuardsRelatedDeletion;

    protected string $model = Volume::class;

    protected string $column = 'label';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    protected string $sortColumn = 'milliliters';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return __('moonshine.volume.title');
    }

    protected function pages(): array
    {
        return [
            VolumeIndexPage::class,
            VolumeFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'label', 'milliliters'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('products');
    }

    protected function deletionGuards(): array
    {
        return [
            'products' => 'Нельзя удалить объём: он используется в товарах.',
        ];
    }
}
