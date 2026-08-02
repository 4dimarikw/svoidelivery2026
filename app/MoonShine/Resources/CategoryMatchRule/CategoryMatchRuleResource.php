<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CategoryMatchRule;

use App\MoonShine\Resources\CategoryMatchRule\Pages\CategoryMatchRuleFormPage;
use App\MoonShine\Resources\CategoryMatchRule\Pages\CategoryMatchRuleIndexPage;
use Domain\Catalog\Models\CategoryMatchRule;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\Icon;

/**
 * Не самостоятельный раздел меню (#[SkipMenu]) — существует только как цель
 * RelationRepeater::make(..., 'matchRules') в CategoryFormPage, тем же
 * способом, что и BeerProductDetailResource у ProductFormPage: любому
 * ModelRelationField нужен ModelResource, даже если у модели нет
 * собственного раздела в каталоге.
 *
 * @extends ModelResource<CategoryMatchRule, CategoryMatchRuleIndexPage, CategoryMatchRuleFormPage, null>
 */
#[Icon('funnel')]
#[SkipMenu]
class CategoryMatchRuleResource extends ModelResource
{
    protected string $model = CategoryMatchRule::class;

    protected string $column = 'value';

    protected function pages(): array
    {
        return [
            CategoryMatchRuleIndexPage::class,
            CategoryMatchRuleFormPage::class,
        ];
    }
}
