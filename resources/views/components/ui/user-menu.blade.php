{{-- Выпадающее меню пользователя в <x-layouts.header> — те же 4 пункта, что
     в <x-ui.account-nav> (профиль/адреса/избранное/выйти), но в компактной
     панели вместо сайдбара. Не общий партиал с account-nav: там нужен
     $active-подсвет и другая обёртка (<nav>, не абсолютно спозиционированная
     панель) — ради 4 ссылок общий компонент с двумя ветками получился бы
     сложнее, чем просто продублировать разметку.

     Триггер — настоящая ссылка на профиль (не <button>), не JS-заглушка:
     панель скрыта через x-cloak, и без Alpine клик по имени просто откроет
     /account/profile, где те же 4 пункта уже есть в <x-ui.account-nav> —
     тот же приём "работает и без JS", что у избранного/степпера корзины.

     x-init сидирует $store.favorites.count — раньше это делала иконка
     избранного в шапке (убрана отсюда же, см. header.blade.php), без
     сидирования здесь счётчик избранного (тут и на карточках товара) после
     гидратации Alpine обнулился бы на каждой странице. --}}
<div
    class="relative"
    x-data="{ open: false }"
    x-init="$store.favorites.count = {{ auth()->user()->favorites()->count() }}"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
>
    <a
        href="{{ route('account.profile.edit') }}"
        x-on:click.prevent="open = ! open"
        aria-haspopup="true"
        :aria-expanded="open"
        class="inline-flex items-center text-body-m text-ink-700 hover:text-rust"
    >
        {{ auth()->user()->name }}
        <x-ui.icon name="chevron-down" :size="16" class="ml-1 transition" ::class="{ 'rotate-180': open }" />
    </a>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="absolute right-0 z-30 mt-2 w-56 grid gap-0.5 rounded-sm border border-hairline bg-cream-50 p-2 shadow-md"
    >
        <a
            href="{{ route('account.profile.edit') }}"
            class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m text-ink-700 hover:bg-cream-200 hover:text-ink-900"
        >{{ __('account.nav.profile') }}</a>

        <a
            href="{{ route('account.addresses.index') }}"
            class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m text-ink-700 hover:bg-cream-200 hover:text-ink-900"
        >
            {{ __('account.address.index_title') }}
            <span class="font-mono text-micro">{{ auth()->user()->addresses()->count() }}</span>
        </a>

        {{-- x-text перекрывает серверное число сразу после гидратации Alpine
             — счётчик держит $store.favorites, общий с account-nav и
             карточками товара, поэтому переключение избранного на любой
             странице отражается тут без перезагрузки. --}}
        <a
            href="{{ route('account.favorites.index') }}"
            class="flex items-center justify-between rounded-sm px-3.5 py-2.5 text-body-m text-ink-700 hover:bg-cream-200 hover:text-ink-900"
        >
            {{ __('account.favorites.title') }}
            <span class="font-mono text-micro" x-text="$store.favorites.count">{{ auth()->user()->favorites()->count() }}</span>
        </a>

        <div class="my-1.5 h-px bg-hairline"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center justify-between rounded-sm px-3.5 py-2.5 text-left text-body-m text-rust hover:bg-danger-50">
                {{ __('layout.nav.logout') }}
            </button>
        </form>
    </div>
</div>
