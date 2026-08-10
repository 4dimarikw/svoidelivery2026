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
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Illuminate\Validation\Rule;
use Infrastructure\Jobs\RefreshProductMediaJob;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Components\When;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Services\Untappd\Facades\Untappd;
use Throwable;
use VI\MoonShineSpatieMediaLibrary\Fields\MediaLibrary;

/**
 * @extends FormPage<ProductResource, Product>
 */
final class ProductFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     *
     * @throws Throwable
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
                            // price закастован в Support\ValueObjects\Price (см. Product::casts()) —
                            // полю нужен голый скаляр в рублях, не объект.
                            ->changeFill(static fn (Product $product) => $product->price?->major())
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
                        Json::make(__('moonshine.product.fields.flags'), 'flags')
                            ->fields(productVariationMetaData()->getMoonshineFields())
                            ->object()
                            // Switcher внутри Json отдаёт 0/1 (int) — приводим к bool,
                            // чтобы тип совпадал с тем, что пишет ResolveFlagsStage при импорте.
                            ->onApply(static function (Product $item, mixed $value): Product {
                                $item->flags = array_map(
                                    static fn (mixed $v): bool => (bool) $v,
                                    (array) $value,
                                );

                                return $item;
                            }),
                    ])->icon('adjustments-horizontal'),

                    Tab::make(__('moonshine.product.tabs.image'), [
                        //                        Image::make(__('moonshine.product.fields.current_image'), 'thumb')
                        //                            ->changePreview(static fn($value) => Thumbnails::make($value))
                        //                            ->disabled()
                        //                            // 'thumb' — вычисляемый accessor (Product::thumb()), не колонка: no-op,
                        //                            // иначе apply() пытается записать это имя как обычную колонку в UPDATE/INSERT.
                        //                            ->onApply(static fn(Product $item): Product => $item),
                        //
                        //                        Image::make(__('moonshine.product.fields.main_image'), 'main_image')
                        //                            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                        //                            ->hint('Загрузка новой картинки заменяет текущую и защищает её от перезаписи фотографией с Untappd при импорте.')
                        //                            // 'main_image' не колонка таблицы — no-op, чтобы data_set не писал мусор в модель.
                        //                            ->onApply(static fn(Product $item): Product => $item)
                        //                            ->onAfterApply(static function (Product $item, mixed $value): Product {
                        //                                if ($value instanceof UploadedFile) {
                        //                                    $item->addMedia($value->getRealPath())
                        //                                        ->usingFileName($value->hashName())
                        //                                        ->withCustomProperties(['source' => 'admin'])
                        //                                        ->toMediaCollection('main');
                        //                                }
                        //
                        //                                return $item;
                        //                            }),
                        When::make(
                            fn () => ! blank($this->getItem()?->beerDetails->untappd_beer_id),
                            fn () => [ActionButton::make('Обновить через Untappd')
                                ->primary()
                                ->method(
                                    'updateImageFromUntappd',
                                    params: ['itemId' => $this->getItem()?->id],
                                    events: [AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'product-label')]
                                )],
                        ),

                        Fragment::make([
                            MediaLibrary::make(__('moonshine.product.fields.main_image'), 'main', formatted: fn ($item) => $item->label)
                                ->keepOriginalFileName()
                                ->dir('upload_for_media_library')
                                ->itemAttributes(fn (string $filename, int $index = 0) => [
                                    'style' => 'width: 100%; max-width: 500px; height: 100%;',
                                ])
                                ->removable(),
                        ])->name('product-label'),
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
            'beerDetails.*.abv' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.ibu' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.plato' => ['nullable', 'numeric', 'min:0'],
            'beerDetails.*.ebc' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    #[AsyncMethod]
    public function updateImageFromUntappd(CrudRequestContract $request)
    {
        $itemId = request()->input('itemId');

        $product = Product::findOrFail($itemId);

        $UBeer = $product?->beerDetails->untappdBeer;

        if (blank($UBeer)) {
            return JsonResponse::make()->toast('Untappd ID не задан', ToastType::WARNING);
        }

        $beerId = $UBeer->beer_id;

        $apiResponse = Untappd::get("beer/info/$beerId");

        if ($apiResponse->meta->code !== 200 || blank($apiResponse->response->beer)) {
            return JsonResponse::make()->toast("Ошибка при получении данных Untappd. bid=$beerId", ToastType::WARNING);
        }

        $UBeer->label = $apiResponse->response->beer->beer_image ?? $apiResponse->response->beer->beer_label;
        $UBeer->rating_count = $apiResponse->response->beer->rating_count;
        $UBeer->rating_score = $apiResponse->response->beer->rating_score;

        $UBeer->save();

        $UBeer->refresh();

        if ($UBeer->label) {
            RefreshProductMediaJob::dispatchSync($product, $UBeer->label);
        }

        return JsonResponse::make()->toast('Изображение обновлено', ToastType::SUCCESS);
    }
}
