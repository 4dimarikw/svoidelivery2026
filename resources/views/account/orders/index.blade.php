{{-- Мои заказы (/account/orders) — список, порт .order-row из брендбука
     (§09), тот же layout/паттерн карточек, что account/addresses/index.blade.php.
     $orders — withCount('orderItems') (Account\OrderController::index()), без
     eager-load связей потяжелее: список не показывает позиции, только шапку
     заказа — детали открываются на show.blade.php. --}}
@php
    // Тон бейджа статуса — свой маппинг под фикс. набор тонов <x-ui.badge>
    // (ok|way|cancel), не завязан на OrderState::getColor() (gray/red/info —
    // другой словарь, для другого места использования в исходном коде).
    $statusTone = fn (string $status) => match ($status) {
        'paid', 'sent', 'completed' => 'ok',
        'cancelled' => 'cancel',
        default => 'way', // new, pending
    };
@endphp

<x-layouts.account active="orders" :title="__('order.history.title')">
    <div class="grid gap-6">
        <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('order.history.title') }}</h2>

        @forelse ($orders as $order)
            <a href="{{ route('account.orders.show', $order) }}" class="block">
                <x-ui.surface tone="paper-2"
                              class="rounded-sm border border-hairline p-5 transition hover:border-ink-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-mono text-body-m text-ink-900">{{ $order->number }}</div>
                            <div class="mt-1 text-caption text-ink-500">
                                {{ $order->created_at?->format('d.m.Y H:i') }}
                                &middot; {{ __('order.history.items_count') }}: {{ $order->order_items_count }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            {{-- <x-ui.badge :tone="$statusTone($order->status->value())">{{ $order->status->humanValue() }}</x-ui.badge> --}}
                            <span class="font-mono text-body-m text-ink-900">{{ $order->amount }}</span>
                        </div>
                    </div>
                </x-ui.surface>
            </a>
        @empty
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('order.history.empty') }}</p>
            </x-ui.surface>
        @endforelse
    </div>
</x-layouts.account>
