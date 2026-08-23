@php
    // no-JS фолбэк: toggle на этой же странице делает back() с session('status'),
    // как и в account/addresses/index.blade.php.
    $noJsStatusMessage = match (session('status')) {
        'favorite-added' => __('account.favorites.added'),
        'favorite-removed' => __('account.favorites.removed'),
        'favorites-cleared' => __('account.favorites.cleared'),
        default => null,
    };
@endphp

<x-layouts.account active="favorites" :title="__('account.favorites.title')">
    <div class="grid gap-6">
        @if ($noJsStatusMessage)
            <x-ui.alert tone="ok">{{ $noJsStatusMessage }}</x-ui.alert>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('account.favorites.title') }}</h2>

            @if ($products->isNotEmpty())
                <form method="POST" action="{{ route('account.favorites.destroy') }}"
                      onsubmit="return confirm(@js(__('account.favorites.clear_confirm')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-caption text-rust hover:underline">{{ __('account.favorites.clear') }}</button>
                </form>
            @endif
        </div>

        @if ($products->isEmpty())
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('account.favorites.empty') }}</p>
            </x-ui.surface>
        @else
            {{-- Та же сетка и та же карточка, что на главной (pages/catalog/_cards.blade.php)
                 — разметка карточки существует в одном месте. --}}
            <div class="grid grid-cols-2 gap-1 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:gap-4">
                @include('pages.catalog._cards')
            </div>

            <div>{{ $products->onEachSide(1)->links() }}</div>
        @endif
    </div>
</x-layouts.account>
