{{--
    Value contract — read this before touching this file.

    A text input can receive its value three ways, chosen automatically:

      A. Plain Blade (no x-model on the tag)
         → renders `value="{{ old($name, $value) }}"` as normal.

      B. Alpine, seeded from the DOM ($fill=true, the default, is why you may
         see BOTH `x-model.fill="…"` and a `value="…"` attribute in the
         rendered HTML — that is not dead markup, do not "clean it up".
         Alpine's `x-model.fill` only overwrites its state on init when the
         state is empty/undefined/null; otherwise the server `value` is the
         seed. `old()` keeps working with zero effort from the caller.
         See node_modules/alpinejs/src/directives/x-model.js — the `.fill`
         guard is `[undefined, null, ''].includes(getValue())`.

      C. Alpine, seeded from PHP ($fill=false) — caller is using
         <x-ui.form :state="[...]"> and wants Alpine's state, not this
         component, to own the value. No `value` attribute is emitted.

    `type="password"` NEVER round-trips a value in any mode — old() does not
    repopulate passwords, and neither does this component.
--}}
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
