<x-layouts.auth :title="__('account.verify.title')">
    <x-ui.auth-card :title="__('account.verify.title')" :subtitle="__('account.verify.subtitle')">
        {{-- Fortify::VERIFICATION_LINK_SENT ('verification-link-sent') is an
             untranslated raw constant — <x-ui.status-alert> would print it
             verbatim, so it's mapped to real copy here instead (same
             technique as resources/views/account/profile.blade.php). --}}
        @if (session('status') === 'verification-link-sent')
            <x-ui.alert tone="ok" class="mb-5">{{ __('account.verify.sent') }}</x-ui.alert>
        @endif

        <x-ui.resend-button
            :action="route('verification.send')"
            :label="__('account.verify.resend')"
        />

        <x-slot:footer>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.btn type="submit" variant="ghost" size="sm">{{ __('layout.nav.logout') }}</x-ui.btn>
            </form>
        </x-slot:footer>
    </x-ui.auth-card>
</x-layouts.auth>
