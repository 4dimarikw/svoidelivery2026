<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\MoonShine\Resources\BeerProductDetail\BeerProductDetailResource;
use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use App\MoonShine\Resources\Container\ContainerResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\ProductBarcode\ProductBarcodeResource;
use App\MoonShine\Resources\Volume\VolumeResource;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<ProductResource, Product>
 */
final class ProductFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        $exists = $this->getItem()?->exists ?? false;

        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('moonshine.product.tabs.main'), [
                        ID::make(),

                        Text::make(__('moonshine.product.fields.external_code'), 'external_code')
                            ->required()
                            ->readonly($exists)
                            ->hint('Ключ идентичности для повторного импорта из 1С. Смена значения у существующего товара разорвёт связь со строкой 1С — на следующем catalog:import будет создан дубликат.'),

                        Text::make(__('moonshine.product.fields.article'), 'article')
                            ->hint('Источник для автогенерации slug.'),

                        Text::make(__('moonshine.product.fields.name'), 'name')->required(),

                        Textarea::make(__('moonshine.product.fields.description'), 'description'),

                        Text::make(__('moonshine.product.fields.slug'), 'slug')
                            ->hint('Заполняется автоматически из article (или name) при создании и при изменении article, если slug не редактировался вручную.'),
                    ])->icon('information-circle'),

                    Tab::make(__('moonshine.product.tabs.classification'), [
                        Select::make(__('moonshine.product.fields.category'), 'category_id')
                            ->options(static fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required(),

                        BelongsTo::make(
                            __('moonshine.product.fields.manufacturer'),
                            'manufacturer',
                            formatted: static fn (Manufacturer $model) => $model->name,
                            resource: ManufacturerResource::class,
                        )
                            ->nullable()
                            ->asyncSearch('name'),

                        BelongsTo::make(
                            __('moonshine.product.fields.volume'),
                            'volume',
                            formatted: static fn (Volume $model) => $model->label,
                            resource: VolumeResource::class,
                        )->nullable(),

                        BelongsTo::make(
                            __('moonshine.product.fields.container'),
                            'container',
                            formatted: static fn (Container $model) => $model->name,
                            resource: ContainerResource::class,
                        )->nullable(),
                    ])->icon('squares-2x2'),

                    Tab::make(__('moonshine.product.tabs.price'), [
                        Number::make(__('moonshine.product.fields.price'), 'price')
                            ->required()
                            ->step(0.01)
                            ->hint('price/stock_quantity/in_stock перезаписываются каждым catalog:import из 1С — правки здесь живут только до следующего синка.'),

                        Number::make(__('moonshine.product.fields.stock_quantity'), 'stock_quantity')
                            ->required(),

                        Switcher::make(__('moonshine.product.fields.in_stock'), 'in_stock'),

                        Enum::make(__('moonshine.product.fields.status'), 'status')->attach(ProductStatus::class),
                    ])->icon('banknotes'),

                    Tab::make(__('moonshine.product.tabs.extra'), [
                        Text::make(__('moonshine.product.fields.brand'), 'brand'),
                        Number::make(__('moonshine.product.fields.package_units'), 'package_units'),
                        Text::make(__('moonshine.product.fields.packaging_raw'), 'packaging_raw'),
                        Number::make(__('moonshine.product.fields.shelf_life_days'), 'shelf_life_days'),
                        Text::make(__('moonshine.product.fields.sales_rating'), 'sales_rating'),

                        Date::make(__('moonshine.product.fields.synced_at'), 'synced_at')
                            ->format('d.m.Y H:i')
                            ->readonly(),

                        Text::make(__('moonshine.product.fields.source_uuid'), 'source_uuid')
                            ->readonly()
                            ->hint('Генерируется автоматически при создании товара из админки.'),
                    ])->icon('adjustments-horizontal'),

                    Tab::make(__('moonshine.product.tabs.image'), [
                        Image::make(__('moonshine.product.fields.current_image'), 'thumb')
                            ->changePreview(static fn ($value) => Thumbnails::make($value))
                            ->disabled()
                            // 'thumb' — вычисляемый accessor (Product::thumb()), не колонка: no-op,
                            // иначе apply() пытается записать это имя как обычную колонку в UPDATE/INSERT.
                            ->onApply(static fn (Product $item): Product => $item),

                        Image::make(__('moonshine.product.fields.main_image'), 'main_image')
                            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                            ->hint('Загрузка новой картинки заменяет текущую и защищает её от перезаписи фотографией с Untappd при импорте.')
                            // 'main_image' не колонка таблицы — no-op, чтобы data_set не писал мусор в модель.
                            ->onApply(static fn (Product $item): Product => $item)
                            ->onAfterApply(static function (Product $item, mixed $value): Product {
                                if ($value instanceof UploadedFile) {
                                    $item->addMedia($value->getRealPath())
                                        ->usingFileName($value->hashName())
                                        ->withCustomProperties(['source' => 'admin'])
                                        ->toMediaCollection('main');
                                }

                                return $item;
                            }),
                    ])->icon('photo'),

                    Tab::make(__('moonshine.product.tabs.beer'), [
                        RelationRepeater::make(__('moonshine.product.fields.beer_details'), 'beerDetails', resource: BeerProductDetailResource::class)
                            ->fields([
                                BelongsTo::make(
                                    __('moonshine.product.fields.beer_style'),
                                    'beerStyle',
                                    formatted: static fn (BeerStyle $model) => $model->name,
                                    resource: BeerStyleResource::class,
                                )->nullable(),
                                Number::make(__('moonshine.product.fields.abv'), 'abv')->step(0.01),
                                Number::make(__('moonshine.product.fields.ibu'), 'ibu')->step(0.01),
                                Number::make(__('moonshine.product.fields.plato'), 'plato')->step(0.01),
                                Number::make(__('moonshine.product.fields.ebc'), 'ebc')->step(0.01),
                            ]),
                    ])->icon('beaker'),

                    Tab::make(__('moonshine.product.tabs.barcodes'), [
                        RelationRepeater::make(__('moonshine.product.fields.barcodes'), 'barcodes', resource: ProductBarcodeResource::class)
                            ->fields([
                                Text::make(__('moonshine.product_barcode.fields.barcode'), 'barcode')->required(),
                            ]),
                    ])->icon('qr-code'),
                ]),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'external_code' => [
                'required', 'string', 'max:64',
                Rule::unique('products', 'external_code')->ignore($item->getKey()),
            ],
            'name' => ['required', 'string', 'max:512'],
            'article' => ['nullable', 'string', 'max:255'],
            'slug' => [
                ...$item->getKey() !== null ? ['required'] : ['nullable'],
                'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($item->getKey()),
            ],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'volume_id' => ['nullable', 'integer', 'exists:volumes,id'],
            'container_id' => ['nullable', 'integer', 'exists:containers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'package_units' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'main_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp'],
            'barcodes.*.barcode' => ['required', 'string', 'max:32'],
            'beerDetails.*.abv' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.ibu' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.plato' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.ebc' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
