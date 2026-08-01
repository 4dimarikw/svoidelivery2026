<x-layouts.auth :title="__('account.forgot.title')">
    <x-ui.auth-card :title="__('account.forgot.title')" :subtitle="__('account.forgot.subtitle')">
        <x-ui.status-alert class="mb-5" />

        <x-ui.form :action="route('password.email')" mode="default">
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

                <x-ui.form-actions>
                    <x-ui.btn type="submit" size="lg" block>{{ __('account.forgot.submit') }}</x-ui.btn>
                </x-ui.form-actions>
            </div>
        </x-ui.form>

        <x-slot:footer>
            <x-ui.link :href="route('login')">{{ __('account.forgot.back') }}</x-ui.link>
        </x-slot:footer>
    </x-ui.auth-card>
</x-layouts.auth>
