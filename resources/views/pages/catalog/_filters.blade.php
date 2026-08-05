{{-- Панель фильтров каталога. Обычная GET-форма на route('home') — работает
     без JS (не 'requestSubmit()' — тот доступен только с Safari 16, таргет
     проекта — Safari >= 13.1). Режим применения переключается флагом
     $autoSubmitFiltersOnChange ниже. --}}
@php
    $selectedCategories = request()->query('categories', []);
    $selectedManufacturers = request()->query('manufacturers', []);
    $selectedVolumes = request()->query('volumes', []);
    $selectedContainers = request()->query('containers', []);

    // Режим применения фильтров.
    // true  — чекбоксы сабмитят форму сразу по change (авто-фильтрация).
    // false — применяется только по кнопке "Применить" (текущий режим).
    // Переключить одной строкой, если понадобится вернуть авто-фильтрацию.
    $autoSubmitFiltersOnChange = false;
@endphp

<x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-5 lg:sticky lg:top-6">
    <form
        method="GET"
        action="{{ route('home') }}"
        @if ($autoSubmitFiltersOnChange)
            x-data
            x-on:change="if (! ['text', 'search', 'number'].includes($event.target.type)) $el.submit()"
        @endif
    >
        <div class="flex items-center justify-between">
            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('catalog.filters.title') }}</h2>
            {{-- Только мобиль — на lg панель не закрывается, кнопка не нужна. --}}
            <button type="button" class="lg:hidden" x-on:click="filtersOpen = false" aria-label="{{ __('catalog.filters.close') }}">
                <x-ui.icon name="x" size="18" class="text-ink-500" />
            </button>
        </div>

        <div class="mt-5">
            <x-ui.input-field name="q" type="search" :label="__('catalog.filters.search')" :value="request('q')" />
        </div>

        <div class="mt-5">
            <x-ui.label>{{ __('catalog.filters.price') }}</x-ui.label>
            <div class="mt-1.5 grid grid-cols-2 gap-3">
                <x-ui.input type="number" name="price_min" min="0" :placeholder="__('catalog.filters.price_min')" :value="request('price_min')" />
                <x-ui.input type="number" name="price_max" min="0" :placeholder="__('catalog.filters.price_max')" :value="request('price_max')" />
            </div>
        </div>

        <div class="mt-5">
            <x-ui.checkbox name="in_stock" value="1" :checked="request()->boolean('in_stock')" :label="__('catalog.filters.in_stock')" />
        </div>

        @if ($categories->isNotEmpty())
            <div class="mt-5">
                <x-ui.select-filter
                    name="categories[]"
                    :label="__('catalog.filters.category')"
                    :options="$categories->pluck('name', 'id')"
                    :selected="$selectedCategories"
                />
            </div>
        @endif

        @if ($manufacturers->isNotEmpty())
            <div class="mt-5">
                <x-ui.select-filter
                    name="manufacturers[]"
                    :label="__('catalog.filters.manufacturer')"
                    :options="$manufacturers->pluck('name', 'id')"
                    :selected="$selectedManufacturers"
                />
            </div>
        @endif

        @if ($volumes->isNotEmpty())
            <div class="mt-5">
                <x-ui.select-filter
                    name="volumes[]"
                    :label="__('catalog.filters.volume')"
                    :options="$volumes->pluck('label', 'id')"
                    :selected="$selectedVolumes"
                />
            </div>
        @endif

        @if ($containers->isNotEmpty())
            <div class="mt-5">
                <x-ui.select-filter
                    name="containers[]"
                    :label="__('catalog.filters.container')"
                    :options="$containers->pluck('name', 'id')"
                    :selected="$selectedContainers"
                />
            </div>
        @endif

        <div class="mt-6 grid gap-3">
            <x-ui.btn type="submit" block>{{ __('catalog.filters.apply') }}</x-ui.btn>
            <x-ui.link :href="route('home')" class="text-center">{{ __('catalog.filters.reset') }}</x-ui.link>
        </div>
    </form>
</x-ui.surface>
