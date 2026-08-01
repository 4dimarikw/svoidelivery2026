{{-- A single input, not `length` boxes. Multi-box OTP inputs break browser
     autofill, screen readers and paste; this one field with
     autocomplete="one-time-code" gets all three for free and matches the
     brand's mono/wide-tracking idiom. Same value contract as <x-ui.input>. --}}
@props([
    'name' => 'code',
    'length' => 6,
    'value' => null,
    'invalid' => null,
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

    $emitValue = ! $isAlpineModel || $fill;
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
    type="text"
    inputmode="numeric"
    autocomplete="one-time-code"
    pattern="[0-9]*"
    maxlength="{{ $length }}"
    name="{{ $name }}"
    id="{{ $id }}"
    @if ($filledModelKey)
        {{ $filledModelKey }}="{{ $modelExpression }}"
    @endif
    @if ($resolvedValue !== null) value="{{ $resolvedValue }}" @endif
    @if ($isInvalid) aria-invalid="true" @endif
    @if ($errorId) aria-describedby="{{ $errorId }}" @endif
    {{ $attributes->class('input text-center font-mono tracking-otp') }}
>
