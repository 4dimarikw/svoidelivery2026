<x-layouts.auth :title="__('account.reset.title')">
    <x-ui.auth-card :title="__('account.reset.title')" :subtitle="__('account.reset.subtitle')">
        <x-ui.form :action="route('password.update')" mode="default">
            <div class="grid gap-3.5">
                {{-- Скрытое поле без классов оформления видимого элемента ввода. --}}
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-ui.input-field
                    name="email"
                    :label="__('account.field.email')"
                    type="email"
                    :value="$request->email"
                    autocomplete="username"
                    readonly
                    required
                />

                <x-ui.password-field
                    name="password"
                    :label="__('account.field.new_password')"
                    :help="__('account.register.password_hint')"
                    autocomplete="new-password"
                    autofocus
                    required
                />

                <x-ui.password-field
                    name="password_confirmation"
                    :label="__('account.field.password_confirmation')"
                    autocomplete="new-password"
                    required
                />

                <x-ui.form-actions>
                    <x-ui.btn type="submit" size="lg" block>{{ __('account.reset.submit') }}</x-ui.btn>
                </x-ui.form-actions>
            </div>
        </x-ui.form>
    </x-ui.auth-card>
</x-layouts.auth>
