{{-- No separate nav links — the catalog lives on `home`, and the logo already
     links there. Stacks vertically on mobile, one row from `sm:` up. Spacing
     uses `space-y-*`/`space-x-*` (margin-based), never flex `gap` — see
     CLAUDE.md's legacy-browser section (Safari < 14.1 has no flex-gap).

     @auth показывает иконку корзины со счётчиком (корзина — не часть
     личного кабинета, см. account-nav.blade.php) и <x-ui.user-menu> — имя
     пользователя с выпадающим меню (профиль/адреса/избранное/выйти), но
     только от `md:` — ниже те же пункты уже есть в <x-ui.mobile-nav>
     (фиксированная нижняя панель, mounted в site.blade.php). Скрываем
     CSS-ом (hidden md:flex), не PHP-условием: x-init ссылки корзины и
     <x-ui.user-menu> сидирует $store.cart.count/$store.favorites.count,
     которые читает нижняя панель — без разметки, просто убранной из DOM,
     счётчики там обнулились бы после гидратации Alpine (Alpine всё равно
     выполняет x-init на display:none элементе).
     Избранное здесь отдельной иконкой не дублируется — это пункт меню.
     x-init сидирует $store.cart.count (resources/js/cart.js) с сервера;
     $store.favorites.count сидируется внутри user-menu.blade.php. --}}
<header class="border-b border-hairline bg-cream-50">
    <div
        class="mx-auto flex max-w-page flex-col  px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('home') }}" class="inline-flex items-center">
            <img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}" class="h-10 w-10 object-contain">
            <span class="ml-3 font-display text-heading-s uppercase text-ink-900">{{ config('app.name') }}</span>
        </a>

        <div class="flex items-center space-x-4">
            @guest
                <x-ui.link :href="route('login')">{{ __('account.login.submit') }}</x-ui.link>
                <x-ui.btn :href="route('register')" size="sm">{{ __('layout.nav.register') }}</x-ui.btn>
            @else
                <div class="hidden items-center space-x-4 md:flex">
                    <a
                        href="{{ route('cart.index') }}"
                        x-data
                        x-init="$store.cart.count = {{ cart()->count() }}"
                        class="inline-flex items-center text-body-m text-ink-700 hover:text-rust"
                        aria-label="{{ __('layout.nav.cart') }}"
                    >
                        <x-ui.icon name="shopping-bag" :size="18"/>
                        <span class="ml-1 font-mono text-micro" x-text="$store.cart.count"></span>
                    </a>
                    <x-ui.user-menu/>
                </div>
            @endguest
        </div>
    </div>
</header>
