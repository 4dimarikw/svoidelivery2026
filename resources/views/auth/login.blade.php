<x-layouts.auth :title="__('account.login.title')">
    <x-ui.auth-card :title="__('account.login.title')" :subtitle="__('account.login.subtitle')">
        {{-- Fortify's PasswordResetResponse redirects HERE (route('login'))
             with session('status') = trans('passwords.reset'). --}}
        <x-ui.status-alert class="mb-5" />

        <x-ui.form :action="route('login.store')" mode="default">
            <div class="grid gap-3.5">
                <x-ui.input-field
                    name="email"
                    :label="__('account.field.email')"
                    type="email"
                    placeholder="you@mail.ru"
                    autocomplete="username"
                    autofocus
                    required
                />

                <x-ui.password-field
                    name="password"
                    :label="__('account.field.password')"
                    autocomplete="current-password"
                    required
                />

                <div class="flex items-center justify-between">
                    <x-ui.checkbox-field name="remember" :label="__('account.login.remember')" />
                    <x-ui.link :href="route('password.request')">{{ __('account.login.forgot') }}</x-ui.link>
                </div>

                <x-ui.form-actions>
                    <x-ui.btn type="submit" size="lg" block>{{ __('account.login.submit') }}</x-ui.btn>
                </x-ui.form-actions>
            </div>
        </x-ui.form>

        <x-slot:footer>
            <p class="text-caption text-ink-500">
                {{ __('account.login.no_account') }}
                <x-ui.link :href="route('register')">{{ __('account.login.create') }}</x-ui.link>
            </p>
        </x-slot:footer>
    </x-ui.auth-card>
</x-layouts.auth>
