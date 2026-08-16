{{-- Карточка одного заказа (/account/orders/{order}) — та же страница,
     что редирект после успешного оформления (OrderController::store()).
     $order приходит с eager-loaded orderCustomer/orderItems.product/
     deliveryType/paymentMethod (Account\OrderController::show()). --}}
@php
    $customer = $order->orderCustomer;
    $addressLine = $customer ? collect([
        $customer->city,
        $customer->address,
    ])->filter()->implode(', ') : null;
@endphp

<x-layouts.account active="orders" :title="$order->number">
    <div class="grid gap-6">
        <div>
            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ $order->number }}</h2>
            <p class="mt-1 text-caption text-ink-500">{{ $order->created_at?->format('d.m.Y H:i') }}</p>
        </div>

        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <div class="grid gap-3">
                @foreach ($order->orderItems as $item)
                    <div class="flex items-center justify-between border-b border-hairline pb-3 last:border-b-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="truncate text-body-m text-ink-900">{{ $item->product?->brand ?: $item->product?->name }}</div>
                            <div class="text-caption text-ink-500">{{ $item->quantity }} &times; {{ $item->price }}</div>
                        </div>
                        <span class="font-mono text-body-m text-ink-900">{{ $item->amount }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-hairline pt-4 font-display text-heading-s uppercase text-ink-900">
                <span>{{ __('account.cart.total_label') }}</span>
                <span class="font-mono">{{ $order->amount }}</span>
            </div>
        </x-ui.surface>

        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <h3 class="mb-3 text-caption uppercase tracking-meta text-ink-500">{{ __('order.delivery_type') }}</h3>
            <p class="text-body-m text-ink-900">{{ $order->deliveryType->title }}</p>

            @if ($order->deliveryType->with_address)
                <p class="mt-2 text-body-m text-ink-900">{{ $addressLine ?: '—' }}</p>
            @endif

            @if ($customer)
                <p class="mt-3 text-caption text-ink-500">
                    {{ trim($customer->last_name.' '.$customer->first_name) }} &middot; {{ $customer->phone }}
                </p>
            @endif
        </x-ui.surface>

        @if ($order->comment)
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                <h3 class="mb-2 text-caption uppercase tracking-meta text-ink-500">{{ __('order.comment') }}</h3>
                <p class="text-body-m text-ink-900">{{ $order->comment }}</p>
            </x-ui.surface>
        @endif
    </div>
</x-layouts.account>
