<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\DTO\CustomerDTO;
use Domain\Order\Models\Order;
use Domain\Profile\Models\Address;
use Illuminate\Support\Facades\Auth;

final class AssignCustomer implements OrderProcessContract
{
    public function __construct(
        protected CustomerDTO $customer
    ) {}

    /**
     * order_customers хранит снимок Address на момент заказа, не FK на
     * изменяемую запись (та же логика, что уже есть у CartItem::price) —
     * если покупатель потом отредактирует/удалит адрес, уже оформленный
     * заказ не должен «поехать». Владение адресом проверяется здесь же
     * (второй раз после OrderRequest — та же defense-in-depth, что везде
     * в проекте, см. Account\AddressController).
     */
    public function handle(Order $order, $next)
    {
        $addressData = [
            'city' => null,
            'street' => null,
            'house' => null,
            'apartment' => null,
            'entrance' => null,
            'floor' => null,
            'intercom' => null,
            'comment' => null,
        ];

        if ($this->customer->addressId !== null) {
            $address = Address::query()->findOrFail($this->customer->addressId);

            abort_unless($address->user_id === Auth::id(), 403);

            $addressData = [
                'city' => $address->city,
                'street' => $address->street,
                'house' => $address->house,
                'apartment' => $address->apartment,
                'entrance' => $address->entrance,
                'floor' => $address->floor,
                'intercom' => $address->intercom,
                'comment' => $address->comment,
            ];
        }

        $order->orderCustomer()->create([
            'first_name' => $this->customer->firstName,
            'last_name' => $this->customer->lastName,
            'phone' => $this->customer->phone,
            ...$addressData,
        ]);

        return $next($order);
    }
}
