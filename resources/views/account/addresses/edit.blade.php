<x-layouts.account active="addresses" :title="__('account.address.edit')">
    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
        <h2 class="mb-5 font-display text-heading-s uppercase text-ink-900">{{ __('account.address.edit') }}</h2>

        @include('account.addresses._form', ['address' => $address])
    </x-ui.surface>
</x-layouts.account>
