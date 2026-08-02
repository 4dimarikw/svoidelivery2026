<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category;

use App\MoonShine\Resources\Category\Pages\CategoryFormPage;
use App\MoonShine\Resources\Category\Pages\CategoryIndexPage;
use App\MoonShine\Support\GuardsRelatedDeletion;
use Domain\Catalog\Models\Category;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<Category, CategoryIndexPage, CategoryFormPage, null>
 */
#[Icon('tag')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(7)]
class CategoryResource extends ModelResource
{
    use GuardsRelatedDeletion;

    protected string $model = Category::class;

    protected string $column = 'name';

    // Форма с 4 вкладками (флаги импорта, репитер правил резолва, pivot
    // свойств) слишком тесная для модалки — create/edit открываются полной
    // страницей. Detail остаётся в модалке, там нечего показывать сложнее.
    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.category.title');
    }

    protected function pages(): array
    {
        return [
            CategoryIndexPage::class,
            CategoryFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code', 'name', 'slug'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount(['products', 'matchRules']);
    }

    protected function deletionGuards(): array
    {
        return [
            'products' => 'Нельзя удалить категорию: она используется в товарах.',
        ];
    }
}
