<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\MoonShine\Resources\BeerProductDetail\BeerProductDetailResource;
use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use App\MoonShine\Resources\Container\ContainerResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\Volume\VolumeResource;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Volume;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<ProductResource>
 */
final class ProductDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),

            Image::make(__('moonshine.product.fields.current_image'), 'thumb')
                ->changePreview(static fn ($value) => Thumbnails::make($value)),

            Text::make(__('moonshine.product.fields.article'), 'article'),
            Text::make(__('moonshine.product.fields.external_code'), 'external_code'),
            Text::make(__('moonshine.product.fields.name'), 'name'),
            Text::make(__('moonshine.product.fields.slug'), 'slug'),
            Textarea::make(__('moonshine.product.fields.description'), 'description'),
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

            Number::make(__('moonshine.product.fields.price'), 'price'),
            Number::make(__('moonshine.product.fields.stock_quantity'), 'stock_quantity'),
            Switcher::make(__('moonshine.product.fields.in_stock'), 'in_stock'),
            Enum::make(__('moonshine.product.fields.status'), 'status')->attach(ProductStatus::class),

            Text::make(__('moonshine.product.fields.brand'), 'brand'),
            Number::make(__('moonshine.product.fields.package_units'), 'package_units'),
            Text::make(__('moonshine.product.fields.packaging_raw'), 'packaging_raw'),
            Number::make(__('moonshine.product.fields.shelf_life_days'), 'shelf_life_days'),

            RelationRepeater::make(__('moonshine.product.fields.beer_details'), 'beerDetails', resource: BeerProductDetailResource::class)
                ->fields([
                    BelongsTo::make(
                        __('moonshine.product.fields.beer_style'),
                        'beerStyle',
                        formatted: static fn (BeerStyle $model) => $model->name,
                        resource: BeerStyleResource::class,
                    ),
                    Number::make(__('moonshine.product.fields.abv'), 'abv'),
                    Number::make(__('moonshine.product.fields.ibu'), 'ibu'),
                    Number::make(__('moonshine.product.fields.plato'), 'plato'),
                    Number::make(__('moonshine.product.fields.ebc'), 'ebc'),
                ]),
        ];
    }
}
