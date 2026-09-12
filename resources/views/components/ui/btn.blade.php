@props([
    'variant' => 'primary',   // primary | ghost | ghost-light | cream | rust | link
    'size' => 'md',           // sm | md | lg
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
    'block' => false,
    'icon' => null,
    'iconAfter' => null,
])

@php
    $variantClass = match ($variant) {
        'ghost' => 'btn-ghost',
        'ghost-light' => 'btn-ghost-light',
        'cream' => 'btn-cream',
        'rust' => 'btn-rust',
        'link' => 'btn-link',
        default => '',
    };

    $sizeClass = match ($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };

    $isDisabled = $disabled || $loading;

    $classes = trim("btn $variantClass $sizeClass ".($block ? 'btn-block' : ''));
@endphp

@if ($href && ! $isDisabled)
    <a
        href="{{ $href }}"
        {{ $attributes->class($classes) }}
    >
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-ui.icon :name="$iconAfter" />
        @endif
    </a>
@elseif ($href)
    {{-- Ссылка с aria-disabled и исключением из порядка Tab всё ещё допускает переход по клику или Enter. Для
       полной блокировки действия используется button. --}}
    <a
        href="{{ $href }}"
        aria-disabled="true"
        tabindex="-1"
        {{ $attributes->class($classes) }}
    >
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-ui.icon :name="$iconAfter" />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @if ($isDisabled) disabled @endif
        @if ($loading) aria-busy="true" @endif
        {{ $attributes->class($classes) }}
    >
        @if ($loading)
            <x-ui.spinner size="16" />
        @elseif ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
        @if ($iconAfter && ! $loading)
            <x-ui.icon :name="$iconAfter" />
        @endif
    </button>
@endif
