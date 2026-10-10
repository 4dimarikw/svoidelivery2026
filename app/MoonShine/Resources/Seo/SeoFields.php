<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo;

use Dimarik\LaravelSeo\Models\Seo;
use Dimarik\LaravelSeo\SeoPathRule;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

final class SeoFields
{
    public static function index(): array
    {
        return [
            ID::make()
                ->sortable(),
            Text::make(__('laravel-seo::moonshine.url'), 'url'),
            Text::make(__('laravel-seo::moonshine.page_title'), 'title'),
            Text::make(__('laravel-seo::moonshine.description'), 'description')
                ->columnSelection(hideOnInit: true),
            Text::make(__('laravel-seo::moonshine.keywords'), 'keywords')
                ->columnSelection(hideOnInit: true),
        ];
    }

    public static function filters(): array
    {
        return [
            Text::make(__('laravel-seo::moonshine.url'), 'url'),
            Text::make(__('laravel-seo::moonshine.page_title'), 'title'),
        ];
    }

    public static function form(): array
    {
        return [
            Box::make([
                ID::make(),
                Text::make(__('laravel-seo::moonshine.url'), 'url')
                    ->required(),
                Text::make(__('laravel-seo::moonshine.page_title'), 'title')
                    ->required(),
                Grid::make([
                    Column::make([Text::make(__('laravel-seo::moonshine.description'), 'description')], 6, 12),
                    Column::make([Text::make(__('laravel-seo::moonshine.keywords'), 'keywords')], 6, 12),
                ]),
                Textarea::make(__('laravel-seo::moonshine.text'), 'text'),
            ]),
        ];
    }

    public static function rules(DataWrapperContract $item): array
    {
        return [
            'title' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],
            'url' => [
                'required',
                'string',
                'max:255',
                new SeoPathRule,
                Rule::unique(Seo::class, 'url')->ignoreModel($item),
            ],
            'description' => ['nullable', 'string', 'max:21845'],
            'keywords' => ['nullable', 'string', 'max:21845'],
            'text' => ['nullable', 'string', 'max:21845'],
        ];
    }

    public static function detail(): array
    {
        return [
            ID::make(),
            Text::make(__('laravel-seo::moonshine.url'), 'url'),
            Text::make(__('laravel-seo::moonshine.page_title'), 'title'),
            Text::make(__('laravel-seo::moonshine.description'), 'description'),
            Text::make(__('laravel-seo::moonshine.keywords'), 'keywords'),
            Textarea::make(__('laravel-seo::moonshine.text'), 'text'),
        ];
    }
}
