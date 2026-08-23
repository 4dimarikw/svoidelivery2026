{{-- Port of the design system's `.acc-nav` (design-system.html lines
     297-305) as inline utilities — single consumer, no pseudo-elements, no
     native-control reset, so it doesn't earn a place in app.css's
     @layer components (see CLAUDE.md's "where CSS lives" rule).

     No icons (the mockup has none) — same "don't stub dead links" rule as
     <x-layouts.header>: payments/promo codes have no page and stay unlinked,
     favorites/orders do (see Domain\Favorite, Domain\Order, CLAUDE.md).
     Cart is NOT here — it's a standalone page
     (/cart, pages/cart.blade.php), not part of the account area; see
     <x-layouts.header> for its icon+counter instead. --}}
@props(['active' => null]) {{-- 'profile' | 'orders' | 'addresses' | 'favorites' --}}

@php
    $itemClass = fn (bool $isActive) => $isActive
        ? 'bg-teal-700 text-cream-100'
        : 'text-ink-700 hover:bg-cream-200 hover:text-ink-900';
@endphp

<nav class="grid gap-0.5 rounded-sm border border-hairline bg-cream-50 p-2">
    <a
        href="{{ route('account.profile.edit') }}"
        class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m {{ $itemClass($active === 'profile') }}"
    >{{ __('account.nav.profile') }}</a>

    <a
        href="{{ route('account.orders.index') }}"
        class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m {{ $itemClass($active === 'orders') }}"
    >
        {{ __('account.nav.orders') }}
        <span class="font-mono text-micro">{{ auth()->user()->ordersCount() }}</span>
    </a>

    <a
        href="{{ route('account.addresses.index') }}"
        class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m {{ $itemClass($active === 'addresses') }}"
    >
        {{ __('account.address.index_title') }}
        <span class="font-mono text-micro">{{ auth()->user()->addressesCount() }}</span>
    </a>

    {{-- x-text перекрывает серверное число сразу после гидратации Alpine —
         счётчик держит $store.favorites (resources/js/favorites.js), общий
         с кнопками избранного на карточках товара, поэтому переключение на
         любой странице отражается тут без перезагрузки. --}}
    <a
        href="{{ route('account.favorites.index') }}"
        class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m {{ $itemClass($active === 'favorites') }}"
    >
        {{ __('account.favorites.title') }}
        <span class="font-mono text-micro" x-data x-text="$store.favorites.count">{{ favorites()->count() }}</span>
    </a>

    <div class="my-1.5 h-px bg-hairline"></div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="flex w-full items-center justify-between rounded-sm px-3.5 py-2.5 text-left text-body-m text-rust hover:bg-danger-50">
            {{ __('layout.nav.logout') }}
        </button>
    </form>
</nav>
