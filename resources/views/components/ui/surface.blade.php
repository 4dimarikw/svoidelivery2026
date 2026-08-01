@props(['tone' => 'paper']) {{-- paper | paper-2 | dark | teal --}}

@php
    $toneClass = match ($tone) {
        'paper-2' => 'bg-cream-50 text-ink-900',
        'dark' => 'on-dark bg-teal-900 text-cream-100',
        'teal' => 'on-dark bg-teal-700 text-cream-100',
        default => 'bg-cream-100 text-ink-900',
    };
@endphp

<div {{ $attributes->class(["rounded-sm", $toneClass]) }}>
    {{ $slot }}
</div>
