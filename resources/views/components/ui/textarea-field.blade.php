@props([
    'name',
    'label' => null,
    'help' => null,
    'value' => null,
    'rows' => 4,
    'required' => false,
    'bag' => 'default',
])

@php
    $id = ui_id($name);
    $helpId = $help ? "{$id}-help" : null;
@endphp

<x-ui.field :name="$name" :label="$label" :for="$id" :help="$help" :required="$required" :bag="$bag">
    <x-ui.textarea
        :name="$name"
        :id="$id"
        :value="$value"
        :rows="$rows"
        :describedby="$helpId"
        :required="$required"
        {{ $attributes }}
    />
</x-ui.field>
