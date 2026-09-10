<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderItem\Pages;

use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\Product\ProductResource;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\OrderItem;
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
use Support\Rules\DecimalPrice;

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
                ->customAttributes(['inputmode' => 'decimal'])
                ->changeFill(static fn (OrderItem $item) => $item->price?->decimal())
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
                        $price?->decimal()
                    );

                    return $fields;
                }),

            BelongsTo::make(__('moonshine.order_item.fields.order'), 'order', resource: OrderResource::class)
                ->readonly($this->getItem()?->exists ?? false),
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
        $model = $item->getOriginal();

        return [
            'price' => ['bail', 'required', new DecimalPrice(1)],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id' => ['int', 'required', 'exists:orders,id', function ($attribute, $value, $fail) use ($model): void {
                if ($model->exists && (int) $value !== (int) $model->getRawOriginal('order_id')) {
                    $fail(__('order.errors.item_transfer_forbidden'));
                }
            }],
        ];
    }
}
