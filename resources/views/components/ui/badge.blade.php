@props(['tone' => 'ok']) {{-- ok | way | cancel --}}

@php
    $toneClass = match ($tone) {
        'way' => 'bg-warn-50 text-warn-700',
        'cancel' => 'bg-danger-50 text-danger-700',
        default => 'bg-teal-100 text-teal-800',
    };
@endphp

<span
    {{ $attributes->class(["inline-flex items-center rounded-sm px-2.5 py-1 font-mono text-badge tracking-badge uppercase", $toneClass]) }}
>{{ $slot }}</span>
