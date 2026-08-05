{{-- Мультивыбор-чекбоксы в виде select-дропдауна: пилюля-триггер с шевроном
     (переиспользует .select-wrap/.select-control — ту же CSS-технику, что у
     нативного <x-ui.select>), раскрывающая панель с клиентским поиском и
     прокручиваемым списком <x-ui.checkbox>. Референс — присланный скриншот
     UI (Производитель/Стиль), без счётчиков товаров у опций.

     No-JS: статический class на панели НЕ содержит `hidden` — до гидратации
     Alpine (и вообще без JS) панель видна в потоке документа, как обычный
     всегда открытый список. Плавающий оверлей (absolute/shadow) появляется
     только когда Alpine реально открыл панель. --}}
@props([
    'name',
    'label',
    'options' => [],
    'selected' => [],
])

@php
    $id = ui_id($name);
    $panelId = $id.'-panel';
@endphp

<div class="select-wrap" x-data="{ open: false, query: '' }" :class="{ 'is-open': open }" x-on:click.outside="open = false">
    {{-- :aria-expanded — одиночное двоеточие корректно: это нативный <button>,
         не <x-...> тег, коллизия Blade-компонентного :attr сюда не применяется. --}}
    <button
        type="button"
        class="input select-control w-full text-left"
        x-on:click="open = !open"
        x-on:keydown.escape.window="open = false"
        :aria-expanded="open"
        aria-controls="{{ $panelId }}"
    >
        {{ $label }}
        @if (count($selected))
            <span class="text-ink-500">({{ count($selected) }})</span>
        @endif
    </button>

    <div
        id="{{ $panelId }}"
        class="mt-2 rounded-sm border border-hairline bg-cream-50 p-3"
        :class="open ? 'block absolute inset-x-0 top-full z-10 shadow-md' : 'hidden'"
    >
        {{-- Обычный нативный input, не <x-ui.input>: это чисто клиентский
             фильтр списка без name, никогда не участвует в сабмите формы —
             прогонять его через контракт x-model/old($name) той компоненты
             смысла не имеет (old(null, ...) вернул бы весь массив old-инпутов). --}}
        <input type="search" x-model="query" placeholder="{{ __('catalog.filters.search') }}" class="input mb-3">

        <div class="grid max-h-64 gap-2 overflow-y-auto">
            @foreach ($options as $value => $optionLabel)
                <div x-show="query === '' || $el.textContent.toLowerCase().includes(query.toLowerCase())">
                    <x-ui.checkbox :name="$name" :value="$value" :checked="in_array((string) $value, $selected)" :label="$optionLabel" />
                </div>
            @endforeach
        </div>
    </div>
</div>
