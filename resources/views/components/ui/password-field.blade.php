@props([
    'name' => 'password',
    'label' => null,
    'help' => null,
    'required' => false,
    'autocomplete' => 'current-password',
])

@php
    $id = ui_id($name);
    $helpId = $help ? "{$id}-help" : null;
@endphp

<x-ui.field :name="$name" :label="$label" :for="$id" :help="$help" :required="$required">
    <div x-data="uiPasswordToggle" class="relative">
        <x-ui.input
            :name="$name"
            :id="$id"
            type="password"
            x-bind:type="type"
            :autocomplete="$autocomplete"
            :describedby="$helpId"
            :required="$required"
            class="pr-11"
            {{ $attributes }}
        />
        <button
            type="button"
            x-on:click="toggle()"
            x-bind:aria-label="show ? @js(__('ui.password.hide')) : @js(__('ui.password.show'))"
            class="absolute inset-y-0 right-0 flex items-center px-3.5 text-ink-500"
        >
            {{-- Both icons render server-side; Alpine only toggles which is
                 visible — a component's `name` prop is resolved at render
                 time and can't be swapped reactively via x-bind. --}}
            <span x-show="!show"><x-ui.icon name="eye" size="18" /></span>
            <span x-show="show" x-cloak><x-ui.icon name="eye-off" size="18" /></span>
        </button>
    </div>
</x-ui.field>
