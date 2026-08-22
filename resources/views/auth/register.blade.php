<x-layouts.auth :title="__('account.register.title')">
    <x-ui.auth-card :title="__('account.register.title')" :subtitle="__('account.register.subtitle')">
        <x-ui.form :action="route('register.store')" mode="default">
            {{-- Вне grid-контейнера ниже — чтобы никогда не стать grid-item
                 и не повлиять на раскладку. См. Infrastructure\Rules\HoneypotRule. --}}
            <x-ui.honeypot />

            <div class="grid gap-3.5">
                <x-ui.input-field
                    name="name"
                    :label="__('account.field.name')"
                    autocomplete="name"
                    autofocus
                    required
                />

                <x-ui.input-field
                    name="email"
                    :label="__('account.field.email')"
                    type="email"
                    placeholder="you@mail.ru"
                    autocomplete="username"
                    required
                />

                <x-ui.password-field
                    name="password"
                    :label="__('account.field.password')"
                    :help="__('account.register.password_hint')"
                    autocomplete="new-password"
                    required
                />

                <x-ui.password-field
                    name="password_confirmation"
                    :label="__('account.field.password_confirmation')"
                    autocomplete="new-password"
                    required
                />

                {{-- Two separate consents, not one combined checkbox — some
                     jurisdictions treat bundled age+terms consent as
                     improper. Validated in
                     app/Actions/Fortify/CreateNewUser.php; neither key is
                     ever persisted to `users`. --}}
                <x-ui.checkbox-field name="age_confirmed" :label="__('account.register.age_gate')" />
                <x-ui.checkbox-field name="terms_accepted" :label="__('account.register.terms_gate')" />

                <x-ui.smart-captcha />

                <x-ui.form-actions>
                    <x-ui.btn type="submit" size="lg" block>{{ __('account.register.submit') }}</x-ui.btn>
                </x-ui.form-actions>
            </div>
        </x-ui.form>

        <x-slot:footer>
            <p class="text-caption text-ink-500">
                {{ __('account.register.have_account') }}
                <x-ui.link :href="route('login')">{{ __('account.register.login') }}</x-ui.link>
            </p>
        </x-slot:footer>
    </x-ui.auth-card>
</x-layouts.auth>
