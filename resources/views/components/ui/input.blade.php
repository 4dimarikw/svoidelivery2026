{{-- Без x-model значение выводится через old(name, value). При fill=true компонент сочетает value и x-model.fill:
   DOM заполняет только пустое, null или undefined состояние Alpine. При fill=false значение задаёт state формы,
   атрибут value не выводится. Пароль не восстанавливается ни в одном режиме. См.
   node_modules/alpinejs/src/directives/x-model.js. --}}
@props([
    'name',
    'value' => null,
    'type' => 'text',
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

    $emitValue = $type !== 'password' && (! $isAlpineModel || $fill);
    $resolvedValue = $emitValue ? old($name, $value) : null;

    $filledModelKey = null;
    $modelExpression = null;
    if ($isAlpineModel && $fill && ! str_contains($modelKey, '.fill')) {
        $modelExpression = $attributes->get($modelKey);
        $filledModelKey = 'x-model.fill'.substr($modelKey, strlen('x-model'));
        $attributes = $attributes->except($modelKey);
    }
@endphp

<input
    @if ($name) name="{{ $name }}" @endif
    id="{{ $id }}"
    type="{{ $type }}"
    @if ($filledModelKey)
        {{ $filledModelKey }}="{{ $modelExpression }}"
    @endif
    @if ($resolvedValue !== null) value="{{ $resolvedValue }}" @endif
    @if ($isInvalid) aria-invalid="true" @endif
    @if ($describedByIds !== '') aria-describedby="{{ $describedByIds }}" @endif
    {{ $attributes->class('input') }}
>
