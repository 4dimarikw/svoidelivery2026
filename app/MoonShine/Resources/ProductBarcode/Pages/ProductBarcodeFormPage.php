<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ProductBarcode\Pages;

use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\ProductBarcode\ProductBarcodeResource;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\ProductBarcode;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<ProductBarcodeResource, ProductBarcode>
 */
final class ProductBarcodeFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                BelongsTo::make(
                    'Товар',
                    'product',
                    formatted: static fn (Product $model) => $model->name,
                    resource: ProductResource::class,
                )
                    ->required()
                    ->asyncSearch('name'),

                Text::make('Штрихкод', 'barcode')
                    ->required()
                    ->hint('Уникален глобально, не привязан к товару в БД. Импорт (PersistBarcodeStage) не переназначает существующий штрихкод другому товару.'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'barcode' => [
                'required', 'string', 'max:32',
                Rule::unique('product_barcodes', 'barcode')->ignore($item->getKey()),
            ],
        ];
    }
}
