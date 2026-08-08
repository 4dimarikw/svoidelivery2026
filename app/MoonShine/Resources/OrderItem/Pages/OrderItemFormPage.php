<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderItem\Pages;

use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\Product\ProductResource;
use Domain\Catalog\Models\Product;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Crud\Collections\Fields;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<OrderItemResource>
 */
class OrderItemFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),

            Text::make(__('moonshine.order_item.fields.price'), 'price')
                ->reactive(),

            Number::make(__('moonshine.order_item.fields.quantity'), 'quantity'),

            BelongsTo::make(
                __('moonshine.order_item.fields.product'),
                'product',
                resource: ProductResource::class
            )->nullable()
                ->reactive(function (Fields $fields, mixed $value): Fields {
                    $price = Product::find($value?->id)?->price;
                    $fields->findByColumn('price')?->setValue(
                        $price ? number_format($price->major(), 2, '.', '') : null
                    );

                    return $fields;
                }),

            BelongsTo::make(__('moonshine.order_item.fields.order'), 'order', resource: OrderResource::class),
        ];
    }

    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
    {
        return parent::modifyFormComponent($component->async(events: [
            AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'fragment-order-amount'),
        ]));
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'price' => ['string', 'required'],
            'quantity' => ['int', 'required'],
            'product_id' => ['int', 'required'],
            'order_id' => ['int', 'required'],
        ];
    }
}
