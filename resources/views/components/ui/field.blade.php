{{-- Обёртка поля выводит содержимое слота. Для типовых элементов используются компоненты x-ui.*-field со своими
   параметрами. --}}
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
