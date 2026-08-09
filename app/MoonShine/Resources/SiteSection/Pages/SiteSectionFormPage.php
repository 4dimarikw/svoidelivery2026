<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteSection\Pages;

use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use Domain\Content\Models\SiteSection;
use Domain\Content\Support\PublicRouteOptions;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends FormPage<SiteSectionResource, SiteSection> */
final class SiteSectionFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Название', 'title')->required(),
                Text::make('Ключ', 'key')->required(),
                Select::make('Именованный маршрут', 'route_name')
                    ->options(app(PublicRouteOptions::class)->get())
                    ->searchable()
                    ->required(),
                Text::make('Якорь без #', 'fragment')->default(''),
                Number::make('Порядок', 'sort_order')->default(0)->min(0),
                Switcher::make('Активен', 'is_active')->default(true),
            ]),
            HasMany::make('Блоки', 'blocks', resource: ContentBlockResource::class)->creatable(),
        ];
    }

    public function prepareForValidation(): void
    {
        request()->merge(['fragment' => ltrim(trim((string)request('fragment')), '#')]);
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'key' => ['required', 'alpha_dash', 'max:255', Rule::unique('site_sections', 'key')->ignore($item->getKey())],
            'route_name' => ['required', Rule::in(array_keys(app(PublicRouteOptions::class)->get()))],
            'fragment' => [
                'present',
                'string',
                'max:255',
                Rule::unique('site_sections', 'fragment')
                    ->where(fn($query) => $query->where('route_name', request('route_name')))
                    ->ignore($item->getKey()),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
