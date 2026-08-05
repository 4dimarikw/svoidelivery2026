<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Container;

use App\MoonShine\Resources\Container\Pages\ContainerFormPage;
use App\MoonShine\Resources\Container\Pages\ContainerIndexPage;
use App\MoonShine\Support\GuardsRelatedDeletion;
use Domain\Catalog\Models\Container;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<Container, ContainerIndexPage, ContainerFormPage, null>
 */
#[Icon('archive-box')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(4)]
class ContainerResource extends ModelResource
{
    use GuardsRelatedDeletion;

    protected string $model = Container::class;

    protected bool $withPolicy = true;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.container.title');
    }

    protected function pages(): array
    {
        return [
            ContainerIndexPage::class,
            ContainerFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code', 'name'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('products');
    }

    protected function deletionGuards(): array
    {
        return [
            'products' => 'Нельзя удалить тару: она используется в товарах.',
        ];
    }
}
