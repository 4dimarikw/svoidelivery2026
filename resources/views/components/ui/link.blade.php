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

    // match(), not "text-$size" interpolation — Tailwind's JIT scanner can't
    // see a class name assembled at runtime; every sibling component (chip,
    // badge, alert) resolves its size/tone the same explicit way.
    $sizeClass = match ($size) {
        'micro' => 'text-micro',
        'body-m' => 'text-body-m',
        default => 'text-caption',
    };
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class(["$sizeClass underline-offset-2 hover:underline", $toneClass]) }}
>{{ $slot }}</a>
