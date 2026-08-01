@props([
    'name',
    'label' => null,
    'help' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
])

@php
    $id = ui_id($name);
    $helpId = $help ? "{$id}-help" : null;
@endphp

<x-ui.field :name="$name" :label="$label" :for="$id" :help="$help" :required="$required">
    <x-ui.select
        :name="$name"
        :id="$id"
        :options="$options"
        :value="$value"
        :placeholder="$placeholder"
        :describedby="$helpId"
        :required="$required"
        {{ $attributes }}
    >{{ $slot }}</x-ui.select>
</x-ui.field>
