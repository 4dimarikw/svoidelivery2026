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
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
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
            Text::make('Артикул', 'article')->sortable(),
            Text::make('Название', 'name')->sortable(),
            Text::make('Категория', 'category.name'),

            BelongsTo::make(
                'Производитель',
                'manufacturer',
                formatted: static fn (Manufacturer $model) => $model->name,
                resource: ManufacturerResource::class,
            ),

            BelongsTo::make(
                'Объём',
                'volume',
                formatted: static fn (Volume $model) => $model->label,
                resource: VolumeResource::class,
            ),

            BelongsTo::make(
                'Тара',
                'container',
                formatted: static fn (Container $model) => $model->name,
                resource: ContainerResource::class,
            ),

            Number::make('Цена', 'price')->sortable(),
            Number::make('Остаток', 'stock_quantity')->sortable(),
            Switcher::make('В наличии', 'in_stock'),
            Enum::make('Статус', 'status')->attach(ProductStatus::class),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Производитель',
                'manufacturer',
                formatted: static fn (Manufacturer $model) => $model->name,
                resource: ManufacturerResource::class,
            )->nullable(),

            Switcher::make('В наличии', 'in_stock'),
            Enum::make('Статус', 'status')->attach(ProductStatus::class)->nullable(),
        ];
    }
}
