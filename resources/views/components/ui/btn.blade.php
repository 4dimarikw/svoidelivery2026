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
    {{-- Disabled link: anchors have no native disabled state, so this is
         aria-disabled + tabindex removal only — it does not stop a click or
         keyboard Enter from navigating. Prefer a real <button> when a control
         needs to be genuinely unclickable. --}}
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
