{{-- Priority chain: Alpine (ajax/enhance mode, live) → $errors (Blade, from
     a real redirect) → nothing. `mode` is the ONLY key this library ever
     reads via @aware — see CLAUDE.md. When mode is 'default' (or there's no
     ancestor <x-ui.form> at all), this renders as a plain static Blade
     partial with no Alpine bindings. --}}
@props([
    'name',
    'bag' => 'default',
    'id' => null,
])
@aware(['mode' => 'default'])

@php
    $id ??= ui_id($name).'-error';
    $message = $errors->getBag($bag)->first($name);
@endphp

@if ($message || $mode !== 'default')
    <p
        id="{{ $id }}"
        role="alert"
        {{ $attributes->class('text-micro text-rust') }}
        @if ($mode !== 'default')
            x-show="errorFor('{{ $name }}')"
            x-text="errorFor('{{ $name }}')"
        @endif
    >{{ $message }}</p>
@endif
