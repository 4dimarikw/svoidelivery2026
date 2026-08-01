{{-- Exists solely so GET /user/confirm-password doesn't 500 — with
     'views' => true it's registered unconditionally by Fortify regardless
     of which features are enabled, and there's no default view binding. --}}
<x-layouts.auth :title="__('account.confirm.title')">
    <x-ui.auth-card :title="__('account.confirm.title')" :subtitle="__('account.confirm.subtitle')">
        <x-ui.form :action="route('password.confirm.store')" mode="default">
            <div class="grid gap-3.5">
                <x-ui.password-field
                    name="password"
                    :label="__('account.field.password')"
                    autocomplete="current-password"
                    autofocus
                    required
                />

                <x-ui.form-actions>
                    <x-ui.btn type="submit" size="lg" block>{{ __('account.confirm.submit') }}</x-ui.btn>
                </x-ui.form-actions>
            </div>
        </x-ui.form>
    </x-ui.auth-card>
</x-layouts.auth>
