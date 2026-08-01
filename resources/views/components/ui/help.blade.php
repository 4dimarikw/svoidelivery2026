@props(['id' => null])

<p @if ($id) id="{{ $id }}" @endif {{ $attributes->class('text-micro text-ink-500') }}>
    {{ $slot }}
</p>
