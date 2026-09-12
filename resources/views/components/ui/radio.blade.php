{{-- При x-model атрибут checked не выводится; начальное состояние передаётся через state формы, как у
   x-ui.checkbox. --}}
@props([
    'name',
    'value',
    'checked' => false,
    'label' => null,
])

@php
    $id = ui_id($name.'-'.$value);

    $isAlpineModel = collect($attributes->getAttributes())->keys()
        ->contains(fn ($key) => $key === 'x-model' || str_starts_with($key, 'x-model.'));

    $isChecked = $isAlpineModel
        ? null
        : (session()->hasOldInput($name) ? old($name) == $value : $checked);
@endphp

<label {{ $attributes->only('class')->class('flex items-center text-body-m text-ink-700 cursor-pointer') }}>
    <input
        type="radio"
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
