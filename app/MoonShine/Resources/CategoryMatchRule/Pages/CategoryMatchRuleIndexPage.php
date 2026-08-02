<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CategoryMatchRule\Pages;

use App\MoonShine\Resources\CategoryMatchRule\CategoryMatchRuleResource;
use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * Не используется напрямую (ресурс #[SkipMenu]) — существует только затем,
 * чтобы RelationRepeater в CategoryFormPage мог отрисовать fields() как
 * дефолтную схему при отсутствии явного ->fields(...).
 *
 * @extends IndexPage<CategoryMatchRuleResource>
 */
final class CategoryMatchRuleIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Enum::make(__('moonshine.category_match_rule.fields.type'), 'type')->attach(CategoryMatchType::class),
            Enum::make(__('moonshine.category_match_rule.fields.match_when'), 'match_when')->attach(CategoryMatchWhen::class)->nullable(),
            Text::make(__('moonshine.category_match_rule.fields.value'), 'value'),
            Number::make(__('moonshine.category_match_rule.fields.priority'), 'priority'),
            Switcher::make(__('moonshine.category_match_rule.fields.is_active'), 'is_active'),
        ];
    }
}
