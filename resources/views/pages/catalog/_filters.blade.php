{{-- Панель фильтров каталога. Обычная GET-форма на route('home') — работает
     без JS (не 'requestSubmit()' — тот доступен только с Safari 16, таргет
     проекта — Safari >= 13.1). Режим применения переключается флагом
     $autoSubmitFiltersOnChange ниже. --}}
@php
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

        {{-- Каждый фильтр рендерит себя сам (Domain\Catalog\Filters\AbstractFilter::
             __toString() → view()) — состав и порядок группы задаются регистрацией
             в AppServiceProvider::boot(), не здесь. --}}
        @foreach ($filters as $filter)
            <div class="mt-5">{!! $filter !!}</div>
        @endforeach

        <div class="mt-6 grid gap-3">
            <x-ui.btn type="submit" block>{{ __('catalog.filters.apply') }}</x-ui.btn>
            <x-ui.link :href="route('home')" class="text-center">{{ __('catalog.filters.reset') }}</x-ui.link>
        </div>
    </form>
</x-ui.surface>
