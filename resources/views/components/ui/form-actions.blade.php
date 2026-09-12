@props(['align' => 'stretch'])

{{-- Вертикальный интервал 14px соответствует сетке полей формы в дизайн-системе. --}}
<div {{ $attributes->class(['grid gap-3.5', 'justify-items-stretch' => $align === 'stretch', 'justify-items-center' => $align === 'center']) }}>
    {{ $slot }}
</div>
