<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerStyle;

use App\MoonShine\Resources\BeerStyle\Pages\BeerStyleFormPage;
use App\MoonShine\Resources\BeerStyle\Pages\BeerStyleIndexPage;
use App\MoonShine\Support\GuardsRelatedDeletion;
use Domain\Catalog\Models\BeerStyle;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<BeerStyle, BeerStyleIndexPage, BeerStyleFormPage, null>
 */
#[Icon('sparkles')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(2)]
class BeerStyleResource extends ModelResource
{
    use GuardsRelatedDeletion;

    protected string $model = BeerStyle::class;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.beer_style.title');
    }

    protected function pages(): array
    {
        return [
            BeerStyleIndexPage::class,
            BeerStyleFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'slug'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount(['beerProductDetails', 'children']);
    }

    protected function deletionGuards(): array
    {
        return [
            'beerProductDetails' => 'Нельзя удалить стиль: он используется в товарах.',
            'children' => 'Нельзя удалить стиль: у него есть дочерние стили.',
        ];
    }
}
