<x-ui.label>{{ $filter->title() }}</x-ui.label>
<div class="mt-1.5 grid grid-cols-2 gap-3">
    <x-ui.input type="number" name="ibu_min" step="0.1" min="0" :placeholder="__('catalog.filters.range_min')" :value="request('ibu_min')" />
    <x-ui.input type="number" name="ibu_max" step="0.1" min="0" :placeholder="__('catalog.filters.range_max')" :value="request('ibu_max')" />
</div>
