<x-layouts.site>
    <div class="mx-auto max-w-page px-6 py-10" x-data="{ filtersOpen: false }">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h1 class="font-display text-heading-m uppercase text-ink-900 sm:text-display-l">{{ __('catalog.title') }}</h1>
                <span
                    class="mt-1 block text-body-m text-ink-500">{{ __('catalog.found', ['count' => $products->total()]) }}</span>
            </div>
            {{-- Только мобиль — на lg панель всегда видна, кнопка не нужна. --}}
            <x-ui.btn
                type="button"
                variant="ghost"
                size="sm"
                class="lg:hidden"
                x-on:click="filtersOpen = !filtersOpen"
                ::aria-expanded="filtersOpen"
                aria-controls="catalog-filters-panel"
            >{{ __('catalog.filters.toggle') }}</x-ui.btn>
        </div>

        {{-- 12-колоночный grid, не flex (запрет на flex-gap, см. CLAUDE.md).
             Панель фильтров — справа на lg (order-2), над сеткой на мобиле
             (естественный порядок DOM). --}}
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
            {{-- :class вместо x-show — до гидратации Alpine (и вообще без JS) рендерится
                 только статический class="... lg:block" без `hidden`, поэтому панель
                 видна на мобиле по умолчанию. После гидратации Alpine добавляет hidden/
                 block поверх статических классов, а lg:block в сгенерированном CSS идёт
                 после базовых утилит и перебивает .hidden на lg: — стандартный паттерн
                 "hidden lg:block", просто с динамическим переключателем вместо статики. --}}
            <div id="catalog-filters-panel" class="lg:order-2 lg:col-span-3 lg:block"
                 :class="filtersOpen ? 'block' : 'hidden'">
                @include('pages.catalog._filters')
            </div>

            <div class="lg:order-1 lg:col-span-9" x-data="catalogList(@js($products->nextPageUrl()))">
                @if ($products->isEmpty())
                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                        <p class="text-body-m text-ink-500">{{ __('catalog.empty') }}</p>
                    </x-ui.surface>
                @else
                    <div x-ref="grid" class="grid grid-cols-2 gap-1 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:gap-1">
                        @include('pages.catalog._cards')
                    </div>

                    <div x-intersect.margin.400px="loadMore()" x-show="nextUrl" class="h-1"></div>

                    <div x-show="loading" x-cloak class="py-8 text-center">
                        <x-ui.spinner size="24"/>
                    </div>

                    <div x-show="error" x-cloak class="py-8 text-center">
                        <x-ui.alert tone="err" class="inline-flex">
                            {{ __('catalog.load_error') }}
                            <button type="button" x-on:click="loadMore()"
                                    class="ml-2 underline">{{ __('catalog.retry') }}</button>
                        </x-ui.alert>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.site>
