@props([
    'seconds' => 60,
    'action',
    'label',
])

@php
    // Split the localized "Resend in :seconds s" string around the
    // placeholder so Alpine can splice in the live countdown without
    // depending on the exact wording/spacing of the translation.
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
