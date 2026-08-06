{{-- No separate nav links — the catalog lives on `home`, and the logo already
     links there. Stacks vertically on mobile, one row from `sm:` up. Spacing
     uses `space-y-*`/`space-x-*` (margin-based), never flex `gap` — see
     CLAUDE.md's legacy-browser section (Safari < 14.1 has no flex-gap).

     Favorites link/counter only in @auth — there's no guest mode for
     favorites (Domain\Favorite, CLAUDE.md), so @guest stays untouched.
     x-init seeds the shared $store.favorites.count (resources/js/favorites.js)
     with the server value; product-card's toggle keeps it live afterwards. --}}
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
                    href="{{ route('account.favorites.index') }}"
                    x-data
                    x-init="$store.favorites.count = {{ auth()->user()->favorites()->count() }}"
                    class="inline-flex items-center text-body-m text-ink-700 hover:text-rust"
                    aria-label="{{ __('layout.nav.favorites') }}"
                >
                    <x-ui.icon name="heart" :size="18" />
                    <span class="ml-1 font-mono text-micro" x-text="$store.favorites.count"></span>
                </a>
                <span class="text-body-m text-ink-700">{{ auth()->user()->name }}</span>
                <x-ui.link :href="route('account.profile.edit')">{{ __('layout.nav.account') }}</x-ui.link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.btn type="submit" variant="ghost" size="sm">{{ __('layout.nav.logout') }}</x-ui.btn>
                </form>
            @endguest
        </div>
    </div>
</header>
