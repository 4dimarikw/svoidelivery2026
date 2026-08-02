<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UntappdBeer;

use App\MoonShine\Resources\UntappdBeer\Pages\UntappdBeerFormPage;
use App\MoonShine\Resources\UntappdBeer\Pages\UntappdBeerIndexPage;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Create отключён: строки создаются только импортом/синком по beer_id
 * (UNIQUE). Delete/MassDelete отключены: beer_product_details.untappd_beer_id
 * — nullOnDelete, ручное удаление молча отвязало бы уже привязанные товары.
 *
 * @extends ModelResource<UntappdBeer, UntappdBeerIndexPage, UntappdBeerFormPage, null>
 */
#[Icon('star')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(5)]
class UntappdBeerResource extends ModelResource
{
    protected string $model = UntappdBeer::class;

    protected string $column = 'name';

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.untappd_beer.title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::DELETE, Action::MASS_DELETE);
    }

    protected function pages(): array
    {
        return [
            UntappdBeerIndexPage::class,
            UntappdBeerFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'beer_id', 'name', 'brewery', 'style'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('beerProductDetails');
    }
}
