<x-ui.label>{{ $filter->title() }}</x-ui.label>
<div class="mt-1.5 grid grid-cols-2 gap-3">
    <x-ui.input type="number" name="price_min" min="0" :placeholder="__('catalog.filters.price_min')" :value="request('price_min')" />
    <x-ui.input type="number" name="price_max" min="0" :placeholder="__('catalog.filters.price_max')" :value="request('price_max')" />
</div>
