{{-- `mode` is the ONLY key any component in this library reads via @aware —
     see CLAUDE.md. Descendants (<x-ui.error>, form-actions submit state,
     etc.) pick it up from however the caller wrote it here. --}}
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
