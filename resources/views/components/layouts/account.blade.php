{{-- Private account area (profile, addresses). Wraps <x-layouts.site> for
     header/footer chrome, adds the account sidebar nav in a 12-col grid —
     stacks to one column below `lg:`. --}}
@props([
    'title' => null,
    'active' => null, // 'profile' | 'addresses'
])
<x-layouts.site :title="$title">
    <div class="mx-auto grid max-w-page grid-cols-1 gap-6 px-6 py-12 lg:grid-cols-12">
        <div class="lg:col-span-3">
            <x-ui.account-nav :active="$active" />
        </div>

        <div class="lg:col-span-9">
            {{ $slot }}
        </div>
    </div>
</x-layouts.site>
