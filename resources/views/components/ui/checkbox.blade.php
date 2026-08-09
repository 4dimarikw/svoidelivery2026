{{-- Checkbox/radio can't use x-model.fill — Alpine's .fill guard only fires
     on [undefined, null, ''], and a checkbox bound to `{ agree: false }` has
     getValue() === false, which is never in that list. So under x-model this
     component emits NO `checked` attribute at all; the caller must seed state
     from PHP via <x-ui.form :state="[...]"> (Path C). Without x-model, this
     falls back to plain Blade (Path A) — including old()-repopulation handling
     for unchecked checkboxes (they don't submit, so old($name) alone would
     wrongly restore the default after a failed POST). --}}
@props([
    'name',
    'value' => '1',
    'checked' => false,
    'label' => null,
])

@php
    $id = ui_id($name.'-'.$value);

    $isAlpineModel = collect($attributes->getAttributes())->keys()
        ->contains(fn ($key) => $key === 'x-model' || str_starts_with($key, 'x-model.'));

    $isChecked = $isAlpineModel
        ? null
        : (old() !== [] ? (bool) old($name) : $checked);
@endphp

<label {{ $attributes->only('class')->class('flex items-center text-body-m text-ink-700 cursor-pointer') }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ $value }}"
        @if ($isChecked) checked @endif
        {{ $attributes->except(['class'])->class('check-control') }}
    >
    @if ($label || $slot->isNotEmpty())
        <span class="ml-2.5">{{ $label ?? $slot }}</span>
    @endif
</label>
