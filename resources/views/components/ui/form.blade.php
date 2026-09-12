{{-- mode — единственный параметр библиотеки, наследуемый через @aware. Его читают вложенные компоненты ошибок и
   действий формы. --}}
@props([
    'action',
    'method' => 'POST',
    'mode' => 'default', // default | enhance | ajax
    'state' => [],
])

@php
    $httpMethod = strtoupper($method);
    $isGet = $httpMethod === 'GET';
    $needsSpoofing = ! in_array($httpMethod, ['GET', 'POST']);
    $formMethod = $isGet ? 'GET' : 'POST';
@endphp

<form
    action="{{ $action }}"
    method="{{ $formMethod }}"
    @if ($mode !== 'default')
        x-data="uiForm({ mode: @js($mode), state: @js($state), errors: @js($errors->messages()) })"
    @endif
    @if ($mode === 'ajax')
        x-on:submit.prevent="submit($el)"
    @endif
    {{ $attributes }}
>
    @unless ($isGet)
        @csrf
    @endunless
    @if ($needsSpoofing)
        @method($httpMethod)
    @endif

    {{ $slot }}
</form>
