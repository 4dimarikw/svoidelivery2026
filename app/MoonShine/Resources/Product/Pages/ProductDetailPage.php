<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\MoonShine\Resources\Container\ContainerResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\Volume\VolumeResource;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Volume;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
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
            Text::make('Артикул', 'article'),
            Text::make('Внешний код', 'external_code'),
            Text::make('Название', 'name'),
            Textarea::make('Описание', 'description'),
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

            Number::make('Цена', 'price'),
            Number::make('Остаток', 'stock_quantity'),
            Switcher::make('В наличии', 'in_stock'),
            Switcher::make('Активен', 'is_active'),
            Date::make('Синхронизирован', 'synced_at')->format('d.m.Y H:i'),
        ];
    }
}
