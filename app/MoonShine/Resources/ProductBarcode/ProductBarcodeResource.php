<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ProductBarcode;

use App\MoonShine\Resources\ProductBarcode\Pages\ProductBarcodeFormPage;
use App\MoonShine\Resources\ProductBarcode\Pages\ProductBarcodeIndexPage;
use Domain\Catalog\Models\ProductBarcode;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<ProductBarcode, ProductBarcodeIndexPage, ProductBarcodeFormPage, null>
 */
#[Icon('qr-code')]
#[Group('Каталог', 'squares-2x2')]
#[Order(5)]
class ProductBarcodeResource extends ModelResource
{
    protected string $model = ProductBarcode::class;

    protected string $column = 'barcode';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    protected array $with = ['product'];

    public function getTitle(): string
    {
        return 'Штрихкоды';
    }

    protected function pages(): array
    {
        return [
            ProductBarcodeIndexPage::class,
            ProductBarcodeFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'barcode'];
    }
}
