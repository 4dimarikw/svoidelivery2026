{{-- Renders session('status') — the session key Fortify's controllers use
     for one-off success messages (password reset link sent, etc). --}}
@props([
    'key' => 'status',
    'tone' => 'ok',
])

@if (session($key))
    <x-ui.alert :tone="$tone" {{ $attributes }}>
        {{ session($key) }}
    </x-ui.alert>
@endif
