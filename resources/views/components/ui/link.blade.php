@props([
    'href' => '#',
    'tone' => 'teal', // teal | ink | rust | light
    'size' => 'caption', // caption | micro | body-m
])

@php
    $toneClass = match ($tone) {
        'ink' => 'text-ink-700 hover:text-ink-900',
        'rust' => 'text-rust hover:text-rust-700',
        'light' => 'text-cream-100/80 hover:text-cream-50',
        default => 'text-teal-800 hover:text-ink-900',
    };
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class(["text-$size underline-offset-2 hover:underline", $toneClass]) }}
>{{ $slot }}</a>
