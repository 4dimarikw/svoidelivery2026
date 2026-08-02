<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CategoryMatchRule\Pages;

use App\MoonShine\Resources\CategoryMatchRule\CategoryMatchRuleResource;
use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\CategoryMatchRule;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * Схема полей, которую RelationRepeater::make(..., 'matchRules') в
 * CategoryFormPage переопределяет собственным ->fields(...) — этот класс лишь
 * даёт ModelRelationField валидный ModelResource (см. CategoryMatchRuleResource).
 *
 * @extends FormPage<CategoryMatchRuleResource, CategoryMatchRule>
 */
final class CategoryMatchRuleFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),

            Enum::make(__('moonshine.category_match_rule.fields.type'), 'type')
                ->attach(CategoryMatchType::class),

            Enum::make(__('moonshine.category_match_rule.fields.match_when'), 'match_when')
                ->attach(CategoryMatchWhen::class)
                ->nullable(),

            Text::make(__('moonshine.category_match_rule.fields.value'), 'value'),

            Number::make(__('moonshine.category_match_rule.fields.priority'), 'priority'),

            Switcher::make(__('moonshine.category_match_rule.fields.is_active'), 'is_active'),
        ];
    }
}
