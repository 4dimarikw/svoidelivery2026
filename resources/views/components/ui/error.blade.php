{{-- Приоритет ошибок: актуальное состояние Alpine, затем errors из Blade. mode наследуется через @aware; в режиме
   default выводится статичное сообщение без Alpine-привязок. --}}
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
