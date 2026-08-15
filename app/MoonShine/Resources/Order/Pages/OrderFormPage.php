<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\PaymentMethod\PaymentMethodResource;
use App\MoonShine\Resources\User\UserResource;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Enums\OrderStatuses;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<OrderResource>
 */
class OrderFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make([
                        ID::make('id'),
                        Number::make('id', formatted: fn ($item) => 'ID: '.$item->id)->previewMode(),

                    ]),
                ])->canSee(fn () => request()->user('moonshine')->isSuperUser()),
                Column::make([
                    Box::make('Данные заказа', [
                        Text::make(__('moonshine.order.fields.number'), 'number'),
                        Text::make(__('moonshine.order.fields.comment'), 'comment'),

                    ]),
                    Box::make([
                        Text::make(__('moonshine.order.fields.amount'), 'amount')->locked()->hint('При изменении состава заказа обновите страницу!'),

                    ]),
                    HasOne::make(__('moonshine.order_customer.title'), 'orderCustomer', resource: OrderCustomerResource::class)
                        ->nullable()
                        ->tabMode(),
                    HasMany::make(__('moonshine.order_item.title'), 'orderItems', resource: OrderItemResource::class)
                        ->creatable()
                        ->tabMode(),
                ], 8, 8),
                Column::make([
                    Box::make('Параметры заказа', [
                        Enum::make(__('moonshine.order.fields.status'), 'status')
                            ->attach(OrderStatuses::class),
                        BelongsTo::make(__('moonshine.user.title'), 'user', resource: UserResource::class),
                        BelongsTo::make(__('moonshine.delivery_type.title'), 'deliveryType', resource: DeliveryTypeResource::class),
                        BelongsTo::make(__('moonshine.payment_method.title'), 'paymentMethod', resource: PaymentMethodResource::class)
                            ->canSee(fn () => request()->user('moonshine')->isSuperUser()),
                    ]),
                ], 4, 4),
            ]),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons()->add(
            ActionButton::make('Отправить файл в 1С')
                ->warning()
                ->method('resendOrder')
        );
    }

    /**
     * @throws Throwable
     */
    #[AsyncMethod]
    public function resendOrder(CrudRequestContract $request, JsonResponse $response)
    {
        try {
            $order = $request->getResource()->getItem();

            if (! $order->id) {
                return JsonResponse::make()->toast('Ошибка при отправке файла', ToastType::ERROR);
            }

            // queueOnFailure: false — это ручной повтор из админки, при
            // провале админ видит ошибку и нажимает кнопку сам; фоновый
            // job дублировал бы то же действие.
            $uploaded = app(UploadOrderToFTP::class)->execute($order->id, queueOnFailure: false);

            if (! $uploaded) {
                return JsonResponse::make()->toast('Ошибка при отправке файла', ToastType::ERROR);
            }

            return JsonResponse::make()->toast('Файл отправлен', ToastType::SUCCESS);
        } catch (Throwable $e) {
            report($e);

            return JsonResponse::make()->toast('Ошибка при отправке файла', ToastType::ERROR);
        }
    }
}
