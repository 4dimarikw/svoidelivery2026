@props(['align' => 'stretch'])

{{-- 14px vertical rhythm — the design system's own field-stack gap (`.auth`
     panel's `gap:14px`). --}}
<div {{ $attributes->class(['grid gap-3.5', 'justify-items-stretch' => $align === 'stretch', 'justify-items-center' => $align === 'center']) }}>
    {{ $slot }}
</div>
