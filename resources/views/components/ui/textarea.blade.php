{{-- Контракт значения совпадает с x-ui.input. Свойство value выводится содержимым textarea; слот не используется
   как источник значения, чтобы исключить лишние пробелы и сохранить работу x-model.fill. --}}
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
