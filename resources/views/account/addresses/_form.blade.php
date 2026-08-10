{{-- Shared by create.blade.php and edit.blade.php. $address is null on create. --}}
@php
    $action = $address ? route('account.addresses.update', $address) : route('account.addresses.store');
    $method = $address ? 'PUT' : 'POST'; // store() is POST-only, update() is PUT — Route::resource convention
@endphp

<x-ui.form :action="$action" :method="$method" mode="ajax">
    @if ($address)
        <div x-show="status === 'ok'" x-cloak class="mb-4">
            <x-ui.alert tone="ok">{{ __('account.address.updated') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-3.5">
        <x-ui.input-field name="label" :label="__('account.address.label')" :value="$address?->label" />
        <x-ui.input-field name="city" :label="__('account.address.city')" :value="$address?->city" required />
        <x-ui.input-field name="address" :label="__('account.address.address')" :value="$address?->address" required />

        <x-ui.textarea-field name="comment" :label="__('account.address.comment')" :value="$address?->comment" />

        <x-ui.checkbox-field name="is_default" :label="__('account.address.is_default')" :checked="(bool) $address?->is_default" />

        <x-ui.form-actions>
            <x-ui.btn type="submit" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ __('account.profile.submit') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center">
                    <x-ui.spinner size="16" class="mr-2" />{{ __('account.profile.saving') }}
                </span>
            </x-ui.btn>
        </x-ui.form-actions>
    </div>
</x-ui.form>
