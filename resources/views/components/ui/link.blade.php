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

    // Полные имена классов в match() доступны сканеру Tailwind; сборка имени из переменных во время выполнения
    // не поддерживается.
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
