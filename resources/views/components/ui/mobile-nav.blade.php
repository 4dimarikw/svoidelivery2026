{{-- Нижнее меню для мобиля — порт мокапа .app-bottom (design-system.html
     §07, строки 894-899). Видно только < md (md:hidden), на десктопе
     навигация остаётся в <x-layouts.header>. Пункты — реальные <a>, не
     <div> как в мокапе (там декоративный прототип без JS).

     grid, не flex — на flex-gap запрет (Safari < 14.1, см. CLAUDE.md);
     тот же приём уже использует account-nav.blade.php.

     @guest видит только публичные маршруты (Каталог, О нас, Войти) —
     Избранное/Корзина/Профиль требуют auth+verified, ссылка на них для
     гостя вела бы на редирект без объяснения.

     Счётчики корзины/избранного НЕ сидируются здесь повторно — сторы уже
     сидируются в header.blade.php ($store.cart) и user-menu.blade.php
     ($store.favorites), оба рендерятся на каждой странице <x-layouts.site>
     раньше этого компонента. Повторный x-init тут просто перезаписал бы
     то же значение — бессмысленно. Серверные числа в бейджах ниже (нужны
     как no-JS фолбэк под x-text) берутся из CartManager/FavoriteManager —
     оба мемоизированы на HTTP-запрос, так что тут это не второй запрос к
     БД, а переиспользование уже прогретого в этом же запросе состояния.

     Активный пункт подсвечивается только цветом (text-teal-700), без
     fill="currentColor" на иконке — в мокапе он есть (.app-bottom
     .nav.active svg{fill:...}), но в этом проекте залитая иконка означает
     конкретно «товар в избранном» (product-card.blade.php, favorite-toggle.blade.php);
     переиспользовать тот же приём для «активная вкладка» смешало бы два
     разных смысла на одной странице. --}}
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
