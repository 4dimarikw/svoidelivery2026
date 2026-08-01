{{-- Same value contract as <x-ui.input> (see that file's header). The one
     difference: a textarea's value is its element content, not a `value`
     attribute, so it's taken here as a `value` PROP and rendered as content —
     slot content is deliberately not used as a value source, since that would
     fight `x-model.fill` (which reads `el.value`, i.e. the text node) and is
     an easy way to leak stray leading/trailing whitespace. --}}
@props([
    'name',
    'value' => null,
    'rows' => 4,
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

    $isInvalid = $invalid ?? $errors->has($name);
    $errorId = $isInvalid ? "{$id}-error" : null;
    $describedByIds = collect([$errorId, $describedby])->filter()->implode(' ');

    $emitValue = ! $isAlpineModel || $fill;
    $resolvedValue = $emitValue ? old($name, $value) : '';

    $filledModelKey = null;
    $modelExpression = null;
    if ($isAlpineModel && $fill && ! str_contains($modelKey, '.fill')) {
        $modelExpression = $attributes->get($modelKey);
        $filledModelKey = 'x-model.fill'.substr($modelKey, strlen('x-model'));
        $attributes = $attributes->except($modelKey);
    }
@endphp
<textarea
    name="{{ $name }}"
    id="{{ $id }}"
    rows="{{ $rows }}"
    @if ($filledModelKey)
        {{ $filledModelKey }}="{{ $modelExpression }}"
    @endif
    @if ($isInvalid) aria-invalid="true" @endif
    @if ($describedByIds !== '') aria-describedby="{{ $describedByIds }}" @endif
    {{ $attributes->class('input textarea') }}
>{{ $resolvedValue }}</textarea>
