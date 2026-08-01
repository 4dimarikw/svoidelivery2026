{{-- Same value contract as <x-ui.input>. `selected` is marked by comparing
     old($name, $value) against each option's key. A `multiple` select always
     fills from the DOM under x-model — Alpine special-cases it, see
     node_modules/alpinejs/src/directives/x-model.js. --}}
@props([
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'invalid' => null,
    'describedby' => null,
    'fill' => true,
    'id' => null,
])

@php
    $id ??= ui_id($name);

    $modelKey = collect($attributes->getAttributes())->keys()
        ->first(fn ($key) => $key === 'x-model' || str_starts_with($key, 'x-model.'));
    $isAlpineModel = $modelKey !== null;
    $isMultiple = $attributes->has('multiple');

    $isInvalid = $invalid ?? $errors->has($name);
    $errorId = $isInvalid ? "{$id}-error" : null;
    $describedByIds = collect([$errorId, $describedby])->filter()->implode(' ');

    $selected = old($name, $value);

    $filledModelKey = null;
    $modelExpression = null;
    if ($isAlpineModel && ($fill || $isMultiple) && ! str_contains($modelKey, '.fill')) {
        $modelExpression = $attributes->get($modelKey);
        $filledModelKey = 'x-model.fill'.substr($modelKey, strlen('x-model'));
        $attributes = $attributes->except($modelKey);
    }
@endphp

<div class="select-wrap">
    <select
        name="{{ $name }}"
        id="{{ $id }}"
        @if ($filledModelKey)
            {{ $filledModelKey }}="{{ $modelExpression }}"
        @endif
        @if ($isInvalid) aria-invalid="true" @endif
        @if ($describedByIds !== '') aria-describedby="{{ $describedByIds }}" @endif
        {{ $attributes->class('input select-control') }}
    >
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            @if ($placeholder)
                <option value="" @if (blank($selected)) selected @endif>{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option
                    value="{{ $optionValue }}"
                    @if ((string) $optionValue === (string) $selected) selected @endif
                >{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>
</div>
