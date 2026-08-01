{{-- Focused on mount so screen readers announce it immediately after a
     failed POST redirect. Relies on <x-ui.alert tone="err"> already setting
     role="alert" on its own root — no extra role/x-data added here (alert
     already carries its own x-data for the dismiss button; a second x-data
     on the same element would just overwrite it). --}}
@props([
    'bag' => 'default',
    'only' => [],
])

@php
    $bagErrors = $errors->getBag($bag);
    $messages = empty($only)
        ? $bagErrors->all()
        : collect($only)->flatMap(fn ($field) => $bagErrors->get($field))->all();
@endphp

@if (count($messages))
    <x-ui.alert
        tone="err"
        x-init="$el.focus()"
        tabindex="-1"
        {{ $attributes }}
    >
        <ul class="list-disc pl-4">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
