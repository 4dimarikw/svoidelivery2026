@props([
    'for' => null,
    'required' => false,
])

{{-- `for` is effectively mandatory — the design system mockup never pairs a
     label with its control via `for`/`id`, which is one of its accessibility
     gaps. Every real usage in this library passes it. --}}
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
