<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category\Pages;

use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\CategoryMatchRule\CategoryMatchRuleResource;
use App\MoonShine\Resources\Property\PropertyResource;
use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<CategoryResource, Category>
 */
final class CategoryFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('moonshine.category.tabs.main'), [
                        ID::make(),

                        Text::make(__('moonshine.category.fields.code'), 'code')
                            ->required()
                            ->hint('Уникальный код категории. Не перезаписывается CategorySeeder после создания.'),

                        Text::make(__('moonshine.category.fields.name'), 'name')
                            ->required(),

                        Text::make(__('moonshine.category.fields.slug'), 'slug')
                            ->hint('Импорт-ключ каталога — по нему CategorySlugResolver находит категорию для товара. Генерируется из name только при пустом значении, повторно не перезаписывается.'),

                        Switcher::make(__('moonshine.category.fields.is_active'), 'is_active'),
                    ])->icon('information-circle'),

                    Tab::make(__('moonshine.category.tabs.import'), [
                        Switcher::make(__('moonshine.category.fields.expects_container'), 'expects_container')
                            ->hint('ResolveContainerStage предупреждает, если у товара этой категории не удалось определить тару.'),

                        Switcher::make(__('moonshine.category.fields.expects_volume'), 'expects_volume')
                            ->hint('ResolveVolumeStage предупреждает, если у товара этой категории не удалось определить объём.'),

                        Switcher::make(__('moonshine.category.fields.price_exempt'), 'price_exempt')
                            ->hint('Товары этой категории не отфильтровываются по normalize.min_price.'),

                        Switcher::make(__('moonshine.category.fields.name_from_article'), 'name_from_article')
                            ->hint('Пустая колонка «Марка» → имя товара берётся из «Артикул» (ResolveProductIdentityStage).'),

                        Text::make(__('moonshine.category.fields.default_brand'), 'default_brand')
                            ->nullable()
                            ->hint('Бренд по умолчанию для пустой колонки «Производитель» (ResolveBrandStage).'),

                        Select::make(__('moonshine.category.fields.container_code'), 'container_code')
                            ->options(static fn (): array => Container::query()->orderBy('name')->pluck('name', 'code')->all())
                            ->nullable()
                            ->hint('Фиксированный код тары независимо от колонки «Упаковка» (ResolveContainerStage).'),
                    ])->icon('adjustments-horizontal'),

                    Tab::make(__('moonshine.category.tabs.match_rules'), [
                        RelationRepeater::make(__('moonshine.category.fields.match_rules'), 'matchRules', resource: CategoryMatchRuleResource::class)
                            ->fields([
                                Enum::make(__('moonshine.category_match_rule.fields.type'), 'type')
                                    ->attach(CategoryMatchType::class),

                                Enum::make(__('moonshine.category_match_rule.fields.match_when'), 'match_when')
                                    ->attach(CategoryMatchWhen::class)
                                    ->nullable(),

                                Text::make(__('moonshine.category_match_rule.fields.value'), 'value')
                                    ->nullable(),

                                Number::make(__('moonshine.category_match_rule.fields.priority'), 'priority'),

                                Switcher::make(__('moonshine.category_match_rule.fields.is_active'), 'is_active'),
                            ]),
                    ])->icon('funnel'),

                    Tab::make(__('moonshine.category.tabs.properties'), [
                        BelongsToMany::make(__('moonshine.category.fields.properties'), 'properties', resource: PropertyResource::class)
                            ->fields([
                                Switcher::make(__('moonshine.category.fields.pivot_is_required'), 'is_required'),
                                Switcher::make(__('moonshine.category.fields.pivot_is_filterable'), 'is_filterable'),
                                Switcher::make(__('moonshine.category.fields.pivot_is_visible'), 'is_visible'),
                                Number::make(__('moonshine.category.fields.pivot_sort_order'), 'sort_order'),
                            ])
                            // pivotModalMode() needs the main record saved
                            // first — the field hides itself on the create
                            // form until then. Fine here: category has to
                            // exist before it can have properties attached.
                            ->pivotModalMode()
                            ->creatable(),
                    ])->icon('adjustments-vertical'),
                ]),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('categories', 'code')->ignore($item->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                ...$item->getKey() !== null ? ['required'] : ['nullable'],
                'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($item->getKey()),
            ],
            'is_active' => ['nullable', 'boolean'],
            'expects_container' => ['nullable', 'boolean'],
            'expects_volume' => ['nullable', 'boolean'],
            'price_exempt' => ['nullable', 'boolean'],
            'name_from_article' => ['nullable', 'boolean'],
            'default_brand' => ['nullable', 'string', 'max:255'],
            'container_code' => ['nullable', 'string', 'max:64', Rule::exists('containers', 'code')],
            'matchRules.*.type' => ['required', Rule::enum(CategoryMatchType::class)],
            'matchRules.*.match_when' => ['nullable', Rule::enum(CategoryMatchWhen::class)],
            'matchRules.*.value' => ['nullable', 'string', 'max:255'],
            'matchRules.*.priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'matchRules.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
