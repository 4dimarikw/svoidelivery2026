{{-- No separate nav links — the catalog lives on `home`, and the logo already
     links there. Stacks vertically on mobile, one row from `sm:` up. Spacing
     uses `space-y-*`/`space-x-*` (margin-based), never flex `gap` — see
     CLAUDE.md's legacy-browser section (Safari < 14.1 has no flex-gap).

     @auth показывает только иконку корзины со счётчиком (корзина — не часть
     личного кабинета, см. account-nav.blade.php) и <x-ui.user-menu> — имя
     пользователя с выпадающим меню (профиль/адреса/избранное/выйти).
     Избранное здесь отдельной иконкой больше не дублируется — это пункт
     меню. x-init сидирует $store.cart.count (resources/js/cart.js) с
     сервера; $store.favorites.count сидируется внутри user-menu.blade.php. --}}
<header class="border-b border-hairline bg-cream-50">
    <div class="mx-auto flex max-w-page flex-col space-y-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
        <a href="{{ route('home') }}" class="inline-flex items-center">
            <img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}" class="h-10 w-10 object-contain">
            <span class="ml-3 font-display text-heading-s uppercase text-ink-900">{{ config('app.name') }}</span>
        </a>

        <div class="flex items-center space-x-4">
            @guest
                <x-ui.link :href="route('login')">{{ __('account.login.submit') }}</x-ui.link>
                <x-ui.btn :href="route('register')" size="sm">{{ __('layout.nav.register') }}</x-ui.btn>
            @else
                <a
                    href="{{ route('cart.index') }}"
                    x-data
                    x-init="$store.cart.count = {{ app(\Domain\Cart\CartManager::class)->count() }}"
                    class="inline-flex items-center text-body-m text-ink-700 hover:text-rust"
                    aria-label="{{ __('layout.nav.cart') }}"
                >
                    <x-ui.icon name="shopping-bag" :size="18" />
                    <span class="ml-1 font-mono text-micro" x-text="$store.cart.count"></span>
                </a>
                <x-ui.user-menu />
            @endguest
        </div>
    </div>
</header>
