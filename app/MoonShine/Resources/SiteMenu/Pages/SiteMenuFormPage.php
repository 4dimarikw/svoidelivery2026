<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenu\Pages;


use App\MoonShine\Resources\SiteMenu\SiteMenuResource;
use App\MoonShine\Resources\SiteMenuItem\SiteMenuItemResource;
use Domain\Content\Models\SiteMenu;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends FormPage<SiteMenuResource, SiteMenu> */
final class SiteMenuFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Название', 'title')->required(),
                Text::make('Ключ', 'key')->required(),
                Switcher::make('Активно', 'is_active')->default(true),
            ]),
            HasMany::make('Пункты меню', 'items', resource: SiteMenuItemResource::class)->creatable(),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'key' => ['required', 'alpha_dash', 'max:255', Rule::unique('site_menus', 'key')->ignore($item->getKey())],
            'is_active' => ['boolean'],
        ];
    }
}
