{{-- При x-model атрибут checked не выводится: начальное состояние задаёт x-ui.form через state. x-model.fill не
   заполняет логическое false. Без x-model используются Blade и old() с учётом того, что снятый флажок не
   отправляет значение. --}}
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
