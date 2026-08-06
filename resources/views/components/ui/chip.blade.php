@props([
    'tone' => 'default', // default | teal | cream | rust | solid | warn
    'dot' => false,
])

@php
    $toneClass = match ($tone) {
        'teal' => 'bg-teal-100 text-teal-800 border-teal-200',
        'cream' => 'bg-cream-200 text-ink-700 border-cream-400',
        'rust' => 'bg-rust-50 text-rust-700 border-rust-200', // rust-700: contrast fix, see CLAUDE.md
        'solid' => 'bg-teal-700 text-cream-100 border-transparent',
        'warn' => 'bg-warn-50 text-warn-700 border-warn-200', // рейтинг Untappd — design-system §06 .chip-untappd
        default => 'bg-cream-50 text-ink-700 border-hairline',
    };
@endphp

<span
    {{ $attributes->class(["inline-flex items-center rounded-pill border px-2.5 py-1.5 font-sans text-micro font-medium tracking-btn", $toneClass]) }}
>
    @if ($dot)
        <span class="mr-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
    @endif
    {{ $slot }}
</span>
