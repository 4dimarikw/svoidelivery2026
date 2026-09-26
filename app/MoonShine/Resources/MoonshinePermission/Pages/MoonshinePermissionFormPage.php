<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonshinePermission\Pages;

use App\MoonShine\Resources\MoonshinePermission\MoonshinePermissionResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Support\PermissionData;
use App\MoonShine\Support\PermissionModels;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use Throwable;

/**
 * @extends FormPage<MoonshinePermissionResource>
 */
class MoonshinePermissionFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     *
     * @throws Throwable
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),
            BelongsTo::make('Роль', 'moonshineUserRole', resource: MoonShineUserRoleResource::class),
            Select::make('Модель', 'model')
                ->options(app(PermissionModels::class)->options())
                ->searchable()
                ->hint('Права не отменяют ограничения меню для SuperUser. Особые ограничения указаны рядом с названием модели.'),
            Json::make('Разрешённые действия', 'permissions')
                ->object()
                ->fields(array_map(
                    static fn (string $key, string $label): Switcher => Switcher::make($label, $key)->default(false),
                    array_keys(PermissionData::LABELS),
                    array_values(PermissionData::LABELS),
                ))
                ->default(array_fill_keys(array_keys(PermissionData::LABELS), false)),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return app(PermissionData::class)->rules($item->getKey(), request('moonshine_user_role_id'));
    }

    /**
     * @param  FormBuilder  $component
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
