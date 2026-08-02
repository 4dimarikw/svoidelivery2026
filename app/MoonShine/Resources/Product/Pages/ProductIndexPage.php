<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\MoonShine\Resources\Container\ContainerResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\Volume\VolumeResource;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Volume;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Infrastructure\Settings\GeneralSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ProductResource>
 */
final class ProductIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('moonshine.product.fields.image'), 'label')
                ->changePreview(fn ($value) => Thumbnails::make($value)),
            Text::make(__('moonshine.product.fields.article'), 'article')->sortable(),
            Text::make(__('moonshine.product.fields.name'), 'name')->sortable(),
            Text::make(__('moonshine.product.fields.category'), 'category.name'),

            BelongsTo::make(
                __('moonshine.product.fields.manufacturer'),
                'manufacturer',
                formatted: static fn (Manufacturer $model) => $model->name,
                resource: ManufacturerResource::class,
            ),

            BelongsTo::make(
                __('moonshine.product.fields.volume'),
                'volume',
                formatted: static fn (Volume $model) => $model->label,
                resource: VolumeResource::class,
            ),

            BelongsTo::make(
                __('moonshine.product.fields.container'),
                'container',
                formatted: static fn (Container $model) => $model->name,
                resource: ContainerResource::class,
            ),

            Number::make(__('moonshine.product.fields.price'), 'price')->sortable(),
            Number::make(__('moonshine.product.fields.stock_quantity'), 'stock_quantity')->sortable(),
            Switcher::make(__('moonshine.product.fields.in_stock'), 'in_stock'),
            Enum::make(__('moonshine.product.fields.status'), 'status')->attach(ProductStatus::class),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                __('moonshine.product.fields.manufacturer'),
                'manufacturer',
                formatted: static fn (Manufacturer $model) => $model->name,
                resource: ManufacturerResource::class,
            )->nullable(),

            Switcher::make(__('moonshine.product.fields.in_stock'), 'in_stock'),
            Enum::make(__('moonshine.product.fields.status'), 'status')->attach(ProductStatus::class)->nullable(),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make(
                'Черновики',
                fn (Builder $query) => $query->where('status', ProductStatus::DRAFT)
            ),
            QueryTag::make(
                'В наличии',
                fn (Builder $query) => $query->where('stock_quantity', '!=', 0)
            ),
            QueryTag::make(
                'Нет в наличии',
                fn (Builder $query) => $query->where('stock_quantity', 0)
            ),
            QueryTag::make(
                'Новинки',
                fn (Builder $query) => $query->where('created_at', '<=', Carbon::now()->subDays(app(GeneralSettings::class)->new_days))
            ),
            QueryTag::make(
                'Не новинки',
                fn (Builder $query) => $query->where('created_at', '>=', Carbon::now()->subDays(app(GeneralSettings::class)->new_days))
            ),
            QueryTag::make(
                'Без UntappdID',
                fn (Builder $query) => $query->whereDoesntHave('product.untappdBeer')
            ),
        ];
    }

    /**
     * @param  TableBuilder  $component
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
