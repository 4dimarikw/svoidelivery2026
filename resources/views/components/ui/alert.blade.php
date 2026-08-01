@props([
    'tone' => 'info', // ok | warn | err | info
    'title' => null,
    'icon' => true,
    'dismissible' => false,
])

@php
    [$toneClass, $iconName, $role] = match ($tone) {
        'ok' => ['bg-teal-100 border-teal-300 text-teal-900', 'check', 'status'],
        'warn' => ['bg-warn-50 border-warn-200 text-warn-700', 'alert-triangle', 'alert'],
        'err' => ['bg-danger-50 border-danger-200 text-danger-700', 'alert-circle', 'alert'],
        default => ['bg-cream-200 border-cream-400 text-ink-700', 'info', 'status'],
    };
@endphp

{{-- flex + gap avoided deliberately (Safari < 14.1, see CLAUDE.md) — spacing
     between icon and body is a margin on the body instead. --}}
<div
    x-data="{ show: true }"
    x-show="show"
    role="{{ $role }}"
    {{ $attributes->class(["flex items-start rounded-sm border p-3.5 text-body-m", $toneClass]) }}
>
    @if ($icon)
        <x-ui.icon :name="$iconName" class="mt-0.5 shrink-0" />
    @endif

    <div class="{{ $icon ? 'ml-3' : '' }} flex-1">
        @if ($title)
            <span class="block font-semibold">{{ $title }}</span>
        @endif
        {{ $slot }}
    </div>

    @if ($dismissible)
        <button
            type="button"
            x-on:click="show = false"
            aria-label="{{ __('ui.alert.close') }}"
            class="ml-3 shrink-0"
        >
            <x-ui.icon name="x" size="16" />
        </button>
    @endif
</div>
