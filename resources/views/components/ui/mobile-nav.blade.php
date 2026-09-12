{{-- Нижняя навигация отображается до md и использует grid для совместимости с Safari без flex-gap. Гостям доступны
   публичные маршруты. Состояния корзины и избранного инициализируются в шапке и user-menu; здесь используются
   готовые хранилища Alpine и мемоизированные серверные счётчики для режима без JavaScript. Активный пункт
   выделяется цветом. --}}
@php
    $isActive = fn (string ...$patterns) => request()->routeIs(...$patterns);
    // FavoriteManager/CartManager сами возвращают 0 гостю без запроса —
    // отдельная проверка auth()->check() тут не нужна.
    $cartCount = cart()->count();
@endphp

<nav
    class="fixed inset-x-0 bottom-0 z-40 grid gap-1.5 border-t border-hairline bg-cream-50 px-3.5 pb-5 pt-2.5 md:hidden {{ auth()->check() ? 'grid-cols-5' : 'grid-cols-4' }}"
    aria-label="{{ __('layout.nav.primary') }}"
>
    @php $active = $isActive('home'); @endphp
    <a
        href="{{ route('home') }}"
        @if ($active) aria-current="page" @endif
        class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge {{ $active ? 'text-teal-700' : 'text-ink-300 hover:text-ink-700' }}"
    >
        <x-ui.icon name="grid" :size="18" />
        {{ __('layout.nav.catalog') }}
    </a>

    {{-- Фильтры — не завязаны на @auth (гость тоже фильтрует каталог, тот
         же принцип, что у самой кнопки фильтров на странице каталога).
         На странице каталога (route home) клик переключает то же локальное
         Alpine-состояние filtersOpen, что и кнопка на самой странице
         (pages/home.blade.php) — но <x-ui.mobile-nav> рендерится сиблингом
         <main>, а не потомком x-data, так что достать до filtersOpen напрямую
         нельзя (Alpine резолвит scope только по предкам). Мост — window
         CustomEvent, тот же приём, что у cart:item-removed/cart:item-updated
         (resources/js/cart.js, cart-stepper.blade.php). На любой другой
         странице панели фильтров просто нет — пункт ведёт на /. --}}
    @php $onCatalogPage = $isActive('home'); @endphp
    @if ($onCatalogPage)
        <button
            type="button"
            x-data
            x-on:click="window.dispatchEvent(new CustomEvent('catalog:filters-toggle'))"
            aria-current="page"
            class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge text-teal-700"
        >
            <x-ui.icon name="sliders" :size="18" />
            {{ __('layout.nav.filters') }}
        </button>
    @else
        {{-- open_filters=1 — читается сервером в pages/home.blade.php при
             сидировании filtersOpen: с другой страницы панель должна
             открыться сразу, не просто перейти на каталог закрытой. --}}
        <a
            href="{{ route('home', ['open_filters' => 1]) }}"
            class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge text-ink-300 hover:text-ink-700"
        >
            <x-ui.icon name="sliders" :size="18" />
            {{ __('layout.nav.filters') }}
        </a>
    @endif

    @auth
        @php $active = $isActive('cart.*'); @endphp
        <a
            href="{{ route('cart.index') }}"
            @if ($active) aria-current="page" @endif
            class="relative grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge {{ $active ? 'text-teal-700' : 'text-ink-300 hover:text-ink-700' }}"
        >
            <x-ui.icon name="shopping-bag" :size="18" />
            {{ __('layout.nav.cart') }}
            <span
                x-data
                x-show="$store.cart.count > 0"
                x-text="$store.cart.count"
                class="absolute right-[calc(50%-24px)] top-0.5 min-w-[17px] rounded-sm bg-rust px-1 text-center font-mono text-[9px] leading-[17px] text-cream-100"
                @if ($cartCount === 0) style="display:none" @endif
            >{{ $cartCount }}</span>
        </a>

        @php $active = $isActive('account.profile.*', 'account.addresses.*'); @endphp
        <a
            href="{{ route('account.profile.edit') }}"
            @if ($active) aria-current="page" @endif
            class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge {{ $active ? 'text-teal-700' : 'text-ink-300 hover:text-ink-700' }}"
        >
            <x-ui.icon name="user" :size="18" />
            {{ __('layout.nav.profile') }}
        </a>
    @endauth

    @php $active = $isActive('about'); @endphp
    <a
        href="{{ route('about') }}"
        @if ($active) aria-current="page" @endif
        class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge {{ $active ? 'text-teal-700' : 'text-ink-300 hover:text-ink-700' }}"
    >
        <x-ui.icon name="info" :size="18" />
        {{ __('layout.nav.about') }}
    </a>

    @guest
        <a
            href="{{ route('login') }}"
            class="grid min-h-[52px] justify-items-center gap-1.5 py-2 font-mono text-badge uppercase tracking-badge text-ink-300 hover:text-ink-700"
        >
            <x-ui.icon name="user" :size="18" />
            {{ __('account.login.submit') }}
        </a>
    @endguest
</nav>
