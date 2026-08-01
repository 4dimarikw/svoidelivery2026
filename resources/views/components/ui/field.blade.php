{{-- Slot-only wrapper — deliberately never renders a control from a `type`
     prop. `select` needs `options`, `textarea` needs `rows`, and a checkbox
     isn't a `.field` at all (its label WRAPS the input). Use the
     <x-ui.*-field> composites for the common cases instead. --}}
@props([
    'label' => null,
    'for' => null,
    'help' => null,
    'name' => null,
    'required' => false,
    'bag' => 'default',
])

@php
    $for ??= $name ? ui_id($name) : null;
    $helpId = $help && $for ? "{$for}-help" : null;
@endphp

<div {{ $attributes->class('grid gap-1.5') }}>
    @if ($label)
        <x-ui.label :for="$for" :required="$required">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($help)
        <x-ui.help :id="$helpId">{{ $help }}</x-ui.help>
    @endif

    @if ($name)
        <x-ui.error :name="$name" :bag="$bag" />
    @endif
</div>
