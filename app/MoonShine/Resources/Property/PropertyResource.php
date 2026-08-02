<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Property;

use App\MoonShine\Resources\Property\Pages\PropertyFormPage;
use App\MoonShine\Resources\Property\Pages\PropertyIndexPage;
use Domain\Catalog\Models\Property;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * Plain CRUD over the `properties` dictionary only — the
 * `category_properties` pivot is deliberately not exposed here:
 * PropertySeeder::run() does `$category->properties()->sync($sync)` on every
 * run, which would silently wipe any admin-edited pivot values
 * (is_required/is_filterable/is_visible/sort_order).
 *
 * @extends ModelResource<Property, PropertyIndexPage, PropertyFormPage, null>
 */
#[Icon('adjustments-horizontal')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(6)]
class PropertyResource extends ModelResource
{
    protected string $model = Property::class;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.property.title');
    }

    protected function pages(): array
    {
        return [
            PropertyIndexPage::class,
            PropertyFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code', 'name'];
    }
}
