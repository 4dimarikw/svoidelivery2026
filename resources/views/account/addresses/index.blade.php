@php
    $noJsStatusMessage = match (session('status')) {
        'address-created' => __('account.address.created'),
        'address-updated' => __('account.address.updated'),
        'address-deleted' => __('account.address.deleted'),
        default => null,
    };
@endphp

<x-layouts.account active="addresses" :title="__('account.address.index_title')">
    <div class="grid gap-6">
        @if ($noJsStatusMessage)
            <x-ui.alert tone="ok">{{ $noJsStatusMessage }}</x-ui.alert>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('account.address.index_title') }}</h2>
            <x-ui.btn :href="route('account.addresses.create')" size="sm">{{ __('account.address.add') }}</x-ui.btn>
        </div>

        @forelse ($addresses as $address)
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center">
                            @if ($address->label)
                                <x-ui.chip tone="teal" class="mr-2">{{ $address->label }}</x-ui.chip>
                            @endif
                            @if ($address->is_default)
                                <span class="font-mono text-micro uppercase tracking-meta text-teal-700">{{ __('account.address.default_marker') }}</span>
                            @endif
                        </div>
                        <p class="mt-2 text-body-m text-ink-900">
                            {{ $address->city }}, {{ $address->address }}
                        </p>
                        @if ($address->comment)
                            <p class="mt-1 text-micro text-ink-500">{{ $address->comment }}</p>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center space-x-4 sm:mt-0">
                        <x-ui.link :href="route('account.addresses.edit', $address)">{{ __('account.address.edit') }}</x-ui.link>

                        <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm(@js(__('account.address.delete_confirm')))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-caption text-rust hover:underline">{{ __('account.address.delete') }}</button>
                        </form>
                    </div>
                </div>
            </x-ui.surface>
        @empty
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('account.address.empty') }}</p>
            </x-ui.surface>
        @endforelse
    </div>
</x-layouts.account>
