<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UntappdBeer\Pages;

use App\MoonShine\Resources\UntappdBeer\UntappdBeerResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<UntappdBeerResource>
 */
final class UntappdBeerIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('moonshine.untappd_beer.fields.label'), 'label')
                ->changePreview(fn($value) => Thumbnails::make($value)),
            Number::make(__('moonshine.untappd_beer.fields.beer_id'), 'beer_id')->sortable(),
            Text::make(__('moonshine.untappd_beer.fields.name'), 'name')->sortable(),
            Text::make(__('moonshine.untappd_beer.fields.brewery'), 'brewery'),
            Text::make(__('moonshine.untappd_beer.fields.style'), 'style'),
            Number::make(__('moonshine.untappd_beer.fields.rating_score'), 'rating_score')->sortable(),
            Number::make(__('moonshine.untappd_beer.fields.rating_count'), 'rating_count'),
            Date::make(__('moonshine.untappd_beer.fields.synced_at'), 'synced_at')->format('d.m.Y H:i')->sortable(),
//            Number::make(__('moonshine.untappd_beer.fields.products_count'), 'beer_product_details_count')->badge(Color::GRAY),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Text::make(__('moonshine.untappd_beer.fields.name'), 'name'),
            Text::make(__('moonshine.untappd_beer.fields.brewery'), 'brewery'),
            Text::make(__('moonshine.untappd_beer.fields.style'), 'style'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make(
                __('moonshine.untappd_beer.query_tags.not_synced'),
                fn(Builder $query) => $query->whereNull('synced_at')
            ),
            QueryTag::make(
                __('moonshine.untappd_beer.query_tags.no_products'),
                fn(Builder $query) => $query->whereDoesntHave('beerProductDetails')
            ),
        ];
    }

    /**
     * @param TableBuilder $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons();
    }
}
