{{-- Общий партиал для всех "мультивыбор чекбоксами" фильтров (категория,
     производитель, объём, тара) — переиспользует готовый <x-ui.select-filter>
     один-в-один, просто питается данными из $filter вместо контроллера. --}}
@php($options = $filter->values())
@if ($options)
    <x-ui.select-filter
        :name="$filter->name()"
        :label="$filter->title()"
        :options="$options"
        :selected="(array) $filter->requestValue(default: [])"
    />
@endif
