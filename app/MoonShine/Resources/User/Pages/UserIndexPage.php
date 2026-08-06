<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\MoonShine\Resources\User\UserResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<UserResource>
 */
final class UserIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('moonshine.user.fields.name'), 'name')->sortable(),
            Email::make(__('moonshine.user.fields.email'), 'email')->sortable(),
            // profile может быть null у легаси-пользователей (создаётся
            // только слушателем Registered) — Text-поле по dot-пути безопасно
            // отрисует пустую строку в этом случае.
            Text::make(__('moonshine.user.fields.phone'), 'profile.phone'),
            Date::make(__('moonshine.user.fields.email_verified_at'), 'email_verified_at')->format('d.m.Y'),
            Date::make(__('moonshine.user.fields.created_at'), 'created_at')->format('d.m.Y')->sortable(),
            Number::make(__('moonshine.user.fields.addresses_count'), 'addresses_count')->badge(Color::GRAY),
            Number::make(__('moonshine.user.fields.favorites_count'), 'favorites_count')->badge(Color::GRAY),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Text::make(__('moonshine.user.fields.name'), 'name'),
            Email::make(__('moonshine.user.fields.email'), 'email'),
        ];
    }

    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component->columnSelection();
    }
}
