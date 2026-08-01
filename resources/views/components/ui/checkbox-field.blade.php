{{-- Not a <x-ui.field> — a checkbox's label WRAPS the input (the design
     system's `.check` layout), it doesn't sit above it in a labelled grid. --}}
@props([
    'name',
    'value' => '1',
    'checked' => false,
    'label' => null,
    'help' => null,
])

<div class="grid gap-1.5">
    <x-ui.checkbox :name="$name" :value="$value" :checked="$checked" :label="$label" {{ $attributes }}>
        {{ $slot }}
    </x-ui.checkbox>

    @if ($help)
        <x-ui.help>{{ $help }}</x-ui.help>
    @endif

    <x-ui.error :name="$name" />
</div>
