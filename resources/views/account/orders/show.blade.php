{{-- Карточка одного заказа (/account/orders/{order}) — та же страница,
     что редирект после успешного оформления (OrderController::store()).
     $order приходит с eager-loaded orderCustomer/orderItems.product/
     deliveryType/paymentMethod (Account\OrderController::show()). --}}
@php
    $customer = $order->orderCustomer;
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
            <h3 class="mb-3 text-caption uppercase tracking-meta text-ink-500">{{ __('order.recipient.title') }}</h3>

            <div class="grid gap-2 text-body-m text-ink-900">
                @if ($customer)
                    <p><span class="text-ink-500">{{ __('order.recipient.name') }}: </span>{{ trim($customer->last_name.' '.$customer->first_name) }}</p>
                    <p><span class="text-ink-500">{{ __('order.recipient.phone') }}: </span>{{ $customer->phone }}</p>
                    <p>
                        <span class="text-ink-500">{{ __('order.messenger') }}: </span>
                        @if ($customer->messenger_url)
                            <x-ui.link :href="$customer->messenger_url" size="body-m">{{ $customer->messenger_url }}</x-ui.link>
                        @else
                            {{ __('order.recipient.not_set') }}
                        @endif
                    </p>
                @endif

                @if ($order->deliveryType->with_address)
                    <p><span class="text-ink-500">{{ __('order.recipient.city') }}: </span>{{ $customer?->city ?: '—' }}</p>
                    <p><span class="text-ink-500">{{ __('order.recipient.address') }}: </span>{{ $customer?->address ?: '—' }}</p>
                @endif

                @if ($order->comment)
                    <p><span class="text-ink-500">{{ __('order.comment') }}: </span>{{ $order->comment }}</p>
                @endif
            </div>
        </x-ui.surface>
    </div>
</x-layouts.account>
