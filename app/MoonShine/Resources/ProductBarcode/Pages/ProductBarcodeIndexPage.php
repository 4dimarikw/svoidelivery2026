<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ProductBarcode\Pages;

use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\ProductBarcode\ProductBarcodeResource;
use Domain\Catalog\Models\Product;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ProductBarcodeResource>
 */
final class ProductBarcodeIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Штрихкод', 'barcode')->sortable(),

            BelongsTo::make(
                'Товар',
                'product',
                formatted: static fn (Product $model) => $model->name,
                resource: ProductResource::class,
            ),
        ];
    }
}
