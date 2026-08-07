{{-- Корзина — самостоятельная страница (/cart), не часть личного кабинета:
     <x-layouts.site>, не <x-layouts.account> — без сайдбара профиля/адресов/
     избранного. Товары строками (cart-line.blade.php) в светлой карточке
     (surface), не голым списком — воспроизводит раздел «10 — Корзина и
     оформление» брендбука (design-system.html:1100-1141): превью/название/
     степпер/цена строки/крестик слева, итог в тёмной teal-панели справа
     (cart-summary.blade.php), прилипающей при скролле длинного списка.

     $cartItems — сами CartItem с eager-loaded product.* (CartController::index()),
     не голые Product: цена строки нужна из CartItem::amount() (снятый снапшот),
     не живой $product->price — см. комментарий в cart-line.blade.php.

     x-show на списке/пустом состоянии/кнопке очистки — поверх серверного
     @if ($cartItems->isEmpty()), не вместо него: первый рендер и без JS
     остаётся корректным по $cartItems, а $store.cart.count (уже сидируется
     ниже через x-init) доигрывает сценарий "удалили крестиком последний
     товар" без перезагрузки — иначе после такого удаления на странице
     зависали бы пустая сетка и панель итога с нулями. --}}
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
            <h1 class="font-display text-heading-m uppercase text-ink-900">{{ __('account.cart.title') }}</h1>

            @if ($cartItems->isNotEmpty())
                <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm(@js(__('account.cart.clear_confirm')))" x-show="$store.cart.count > 0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-caption text-rust hover:underline">{{ __('account.cart.clear') }}</button>
                </form>
            @endif
        </div>

        <div x-show="$store.cart.count === 0" @if ($cartItems->isNotEmpty()) x-cloak @endif>
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('account.cart.empty') }}</p>
            </x-ui.surface>
        </div>

        {{-- 8/4 — та же 12-колоночная сетка, что в pages/home.blade.php
             (grid, не flex — CLAUDE.md запрещает только flex-gap). --}}
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12" x-show="$store.cart.count > 0" @if ($cartItems->isEmpty()) x-cloak @endif>
            <div class="lg:col-span-8">
                <x-ui.surface tone="paper-2" class="border border-hairline px-5">
                    @foreach ($cartItems as $cartItem)
                        <x-ui.cart-line :cart-item="$cartItem" />
                    @endforeach
                </x-ui.surface>
            </div>

            <div class="lg:sticky lg:top-6 lg:col-span-4 lg:self-start">
                <x-ui.cart-summary :count="$cart->count()" :amount="$amount" />
            </div>
        </div>
    </div>
</x-layouts.site>
