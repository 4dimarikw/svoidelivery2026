{{-- После неуспешной отправки блок получает фокус для объявления ошибок средствами чтения с экрана. Роль alert и
   состояние закрытия задаёт x-ui.alert. --}}
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
