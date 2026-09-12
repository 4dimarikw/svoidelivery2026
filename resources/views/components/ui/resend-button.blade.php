@props([
    'seconds' => 60,
    'action',
    'label',
])

@php
    // Перевод разделяется по заполнителю :seconds, чтобы Alpine вставлял отсчёт независимо от формулировки
    // сообщения.
    $waitTemplate = __('ui.resend.wait', ['seconds' => '§']);
    [$waitPrefix, $waitSuffix] = explode('§', $waitTemplate) + ['', ''];
@endphp

<form method="POST" action="{{ $action }}" x-data="uiCountdown({{ $seconds }})" {{ $attributes }}>
    @csrf
    <x-ui.btn type="submit" variant="link" x-bind:disabled="disabled" x-bind:aria-disabled="disabled ? 'true' : null">
        <span x-show="!disabled">{{ $label }}</span>
        <span x-show="disabled" x-cloak x-text="@js($waitPrefix) + remaining + @js($waitSuffix)"></span>
    </x-ui.btn>
</form>
