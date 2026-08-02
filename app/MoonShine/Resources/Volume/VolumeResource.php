<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Volume;

use App\MoonShine\Resources\Volume\Pages\VolumeFormPage;
use App\MoonShine\Resources\Volume\Pages\VolumeIndexPage;
use Domain\Catalog\Models\Volume;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\MoonShineException;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\Enums\ToastType;

/**
 * @extends ModelResource<Volume, VolumeIndexPage, VolumeFormPage, null>
 */
#[Icon('beaker')]
#[Group('Каталог', 'squares-2x2')]
#[Order(0)]
class VolumeResource extends ModelResource
{
    protected string $model = Volume::class;

    protected string $column = 'label';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    protected string $sortColumn = 'milliliters';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return 'Объёмы';
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

    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        /** @var Volume $volume */
        $volume = $item->getOriginal();

        if ($volume->products()->exists()) {
            $message = 'Нельзя удалить объём: он используется в товарах.';

            toast($message, ToastType::ERROR);

            throw new MoonShineException($message);
        }

        return $item;
    }
}
