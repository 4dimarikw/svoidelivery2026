{{-- Автосабмит только у сортировки, независимо от общего переключателя
     $autoSubmitFiltersOnChange в _filters.blade.php (тот — про все контролы
     разом, тут — точечно про один select). Обработчик формы сначала
     корректирует диапазоны, затем вызывает submit(), не requestSubmit() — тот доступен только с Safari 16,
     таргет проекта — Safari >= 13.1 (та же причина, что у соседнего
     переключателя). --}}
<x-ui.select-field
    name="sort"
    :label="$filter->title()"
    :options="$filter->values()"
    :value="$filter->requestValue(default: \Domain\Catalog\Enums\ProductSort::default()->value)"
    x-on:change="submitFilters()"
/>
