<x-ui.label>{{ $filter->title() }}</x-ui.label>
<div class="mt-1.5 grid grid-cols-2 gap-3">
    <x-ui.input type="number" name="abv_min" step="0.1" min="0" :placeholder="__('catalog.filters.price_min')" :value="request('abv_min')" />
    <x-ui.input type="number" name="abv_max" step="0.1" min="0" :placeholder="__('catalog.filters.price_max')" :value="request('abv_max')" />
</div>
