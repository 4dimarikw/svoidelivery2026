@props([
    'name',
    'label' => null,
    'help' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'autocomplete' => null,
])

@php
    $id = ui_id($name);
    $helpId = $help ? "{$id}-help" : null;
@endphp

<x-ui.field :name="$name" :label="$label" :for="$id" :help="$help" :required="$required">
    <x-ui.input
        :name="$name"
        :id="$id"
        :type="$type"
        :value="$value"
        :placeholder="$placeholder"
        :autocomplete="$autocomplete"
        :describedby="$helpId"
        :required="$required"
        {{ $attributes }}
    />
</x-ui.field>
