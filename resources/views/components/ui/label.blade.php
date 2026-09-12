@props([
    'for' => null,
    'required' => false,
])

{{-- Атрибут for связывает подпись с id элемента ввода и требуется во всех местах использования. --}}
<label
    for="{{ $for }}"
    {{ $attributes->class('font-mono text-field-label uppercase text-ink-500') }}
>
    {{ $slot }}
    @if ($required)
        <span aria-hidden="true" class="text-rust">*</span>
        <span class="sr-only">, {{ __('ui.required') }}</span>
    @endif
</label>
