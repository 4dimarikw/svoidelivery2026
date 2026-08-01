@props([
    'codes' => [],
    'copyable' => true,
])

@php
    $joined = implode("\n", $codes);
@endphp

<div {{ $attributes->class('rounded-sm border border-hairline bg-cream-50 p-4') }}>
    <ul class="grid grid-cols-2 gap-2 font-mono text-body-m text-ink-900">
        @foreach ($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>

    @if ($copyable)
        <div x-data="uiCopy(@js($joined))" class="mt-4">
            <x-ui.btn type="button" variant="cream" size="sm" x-on:click="copy()">
                <span x-show="!copied">{{ __('ui.copy.action') }}</span>
                <span x-show="copied" x-cloak>{{ __('ui.copy.copied') }}</span>
            </x-ui.btn>
        </div>
    @endif
</div>
