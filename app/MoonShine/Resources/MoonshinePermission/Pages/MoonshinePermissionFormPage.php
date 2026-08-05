<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonshinePermission\Pages;

use App\MoonShine\Resources\MoonshinePermission\MoonshinePermissionResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\Enum;
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
            BelongsTo::make('Role', 'moonshineUserRole', resource: MoonShineUserRoleResource::class),
            Select::make('Model', 'model')
                ->options($this->modelOptions())
                ->searchable(),
            Json::make('Permissions', 'permissions')
                ->keyValue(
                    keyField: Enum::make('Key')
                        ->attach(Ability::class),
                    valueField: Switcher::make('Value'),
                ),
        ];
    }

    /**
     * @return array<class-string, string>
     */
    private function modelOptions(): array
    {
        $options = [];

        foreach (moonshine()->getResources() as $resource) {
            if (! $resource instanceof ModelResource) {
                continue;
            }

            $model = $resource->getModel()::class;

            $options[$model] = class_basename($model).' — '.$resource->getTitle();
        }

        asort($options);

        return $options;
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
        return [
            'moonshine_user_role_id' => ['required', 'integer', 'exists:moonshine_user_roles,id'],
            'model' => ['required', 'string'],
            'permissions' => ['nullable', 'array'],
        ];
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
