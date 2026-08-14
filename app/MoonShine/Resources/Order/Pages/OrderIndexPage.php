<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\PaymentMethod\PaymentMethodResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Traits\CustomUI;
use Domain\Auth\Models\User;
use Domain\Order\Actions\CreateTestOrder;
use Domain\Order\Actions\UploadOrderToFTP;
use Domain\Order\Enums\OrderStatuses;
use Infrastructure\Settings\SiteSettings;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Modal;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<OrderResource>
 */
class OrderIndexPage extends IndexPage
{
    use CustomUI;

    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),

            Text::make(__('moonshine.order.fields.number'), 'number'),

            BelongsTo::make(__('moonshine.order.fields.user'), 'user', resource: UserResource::class),

            Date::make(__('moonshine.order.fields.created_at'), 'created_at'),

            HasOne::make(__('moonshine.order_customer.title'), 'orderCustomer', resource: OrderCustomerResource::class)
                ->modifyTable(
                    fn (TableBuilder $table) => $this->modifyTableVertical($table)
                )
                ->modalMode(
                    modifyButton: function (ActionButtonContract $button, HasOne $ctx) {
                        $button->toggleModal('order-customer-modal-'.$this->getResource()->getItem()->id);

                        return $button;
                    },
                    modifyModal: function (Modal $modal, ActionButtonContract $ctx) {
                        $modal->autoClose(false);
                        $modal->name('order-customer-modal-'.$this->getResource()->getItem()->id);

                        return $modal;
                    }
                ),

            HasMany::make(__('moonshine.order_item.title'), 'orderItems', resource: OrderItemResource::class)
                ->modalMode(
                    modifyButton: function (ActionButtonContract $button, HasMany $ctx) {
                        $button->toggleModal('order-items-modal-'.$this->getResource()->getItem()->id);

                        return $button;
                    },
                    modifyModal: function (Modal $modal, ActionButtonContract $ctx) {
                        $modal->autoClose(false)
                            ->name('order-items-modal-'.$this->getResource()->getItem()->id)
                            ->wide();

                        return $modal;
                    }
                ),

            Text::make(__('moonshine.order.fields.amount'), 'amount'),

            Text::make(__('moonshine.order.fields.comment'), 'comment'),

            Enum::make(__('moonshine.order.fields.status'), 'status')
                ->canSee(fn () => request()->user('moonshine')->isSuperUser())
                ->attach(OrderStatuses::class),

            BelongsTo::make(__('moonshine.delivery_type.title'), 'deliveryType', resource: DeliveryTypeResource::class)
                ->badge(),

            BelongsTo::make(__('moonshine.payment_method.title'), 'paymentMethod', resource: PaymentMethodResource::class)
                ->canSee(fn () => request()->user('moonshine')->isSuperUser())
                ->badge(),

        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @return list<Metric>
     */
    protected function metrics(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons();
    }

    protected function topLeftButtons(): ListOf
    {
        return parent::topLeftButtons()
            ->add(
                ActionButton::make('Создать тестовый заказ', '#')
                    ->secondary()
                    ->method('createTestOrder')
            );
    }

    /**
     * @throws Throwable
     */
    #[AsyncMethod]
    public function createTestOrder()
    {
        try {
            $testUserEmail = app(SiteSettings::class)->test_user_email;

            $user = User::query()->where('email', $testUserEmail)->firstOrFail();

            $order = app(CreateTestOrder::class)->execute($user->id);

            app(UploadOrderToFTP::class)->execute($order->id);

            return JsonResponse::make()->toast('Тестовый заказ создан', ToastType::SUCCESS)->events(
                [AlpineJs::event(
                    JsEvent::TABLE_UPDATED, 'index-table-order-resource')]
            );
        } catch (Throwable $e) {
            report($e);

            return JsonResponse::make()->toast('Ошибка создания тестового заказа', ToastType::ERROR);
        }
    }
}
