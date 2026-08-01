@props(['size' => 18])

<x-ui.icon
    name="loader"
    :size="$size"
    {{ $attributes->class('animate-spin') }}
/>
