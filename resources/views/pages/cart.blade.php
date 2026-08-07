{{-- Корзина — самостоятельная страница (/cart), не часть личного кабинета:
     <x-layouts.site>, не <x-layouts.account> — без сайдбара профиля/адресов/
     избранного. Товары строками (cart-line.blade.php), не карточками —
     воспроизводит раздел «10 — Корзина и оформление» брендбука
     (design-system.html:1100-1141): превью/название/степпер/цена строки/
     крестик слева, итог в тёмной teal-панели справа (cart-summary.blade.php).

     $cartItems — сами CartItem с eager-loaded product.* (CartController::index()),
     не голые Product: цена строки нужна из CartItem::amount() (снятый снапшот),
     не живой $product->price — см. комментарий в cart-line.blade.php. --}}
@php
    // no-JS фолбэк: increase/decrease/destroy/clear на этой же странице делают
    // back()/redirect() с session('status'), как и в account/favorites/index.blade.php.
    $noJsStatusMessage = match (session('status')) {
        'cart-item-added' => __('account.cart.added'),
        'cart-item-removed' => __('account.cart.removed'),
        'cart-cleared' => __('account.cart.cleared'),
        default => null,
    };
@endphp

<x-layouts.site :title="__('account.cart.title')">
    <div class="mx-auto max-w-page px-6 py-10" x-init="$store.cart.amount = @js((string) $amount)">
        @if ($noJsStatusMessage)
            <x-ui.alert tone="ok" class="mb-6">{{ $noJsStatusMessage }}</x-ui.alert>
        @endif

        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-display text-heading-m uppercase text-ink-900 sm:text-display-l">{{ __('account.cart.title') }}</h1>

            @if ($cartItems->isNotEmpty())
                <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm(@js(__('account.cart.clear_confirm')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-caption text-rust hover:underline">{{ __('account.cart.clear') }}</button>
                </form>
            @endif
        </div>

        @if ($cartItems->isEmpty())
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('account.cart.empty') }}</p>
            </x-ui.surface>
        @else
            {{-- 8/4 — та же 12-колоночная сетка, что в pages/home.blade.php
                 (grid, не flex — CLAUDE.md запрещает только flex-gap). --}}
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    @foreach ($cartItems as $cartItem)
                        <x-ui.cart-line :cart-item="$cartItem" />
                    @endforeach
                </div>

                <div class="lg:col-span-4">
                    <x-ui.cart-summary :count="$cart->count()" :amount="$amount" />
                </div>
            </div>
        @endif
    </div>
</x-layouts.site>
