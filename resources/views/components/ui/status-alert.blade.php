{{-- Однократное сообщение об успехе из session('status'), используемое обработчиками Fortify. --}}
@props([
    'key' => 'status',
    'tone' => 'ok',
])

@if (session($key))
    <x-ui.alert :tone="$tone" {{ $attributes }}>
        {{ session($key) }}
    </x-ui.alert>
@endif
