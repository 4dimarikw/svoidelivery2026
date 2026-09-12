{{-- Подпись оборачивает флажок вместе с полем ввода. --}}
@props([
    'name',
    'value' => '1',
    'checked' => false,
    'label' => null,
    'help' => null,
    'bag' => 'default',
])

<div class="grid gap-1.5">
    <x-ui.checkbox :name="$name" :value="$value" :checked="$checked" :label="$label" {{ $attributes }}>
        {{ $slot }}
    </x-ui.checkbox>

    @if ($help)
        <x-ui.help>{{ $help }}</x-ui.help>
    @endif

    <x-ui.error :name="$name" :bag="$bag" />
</div>
