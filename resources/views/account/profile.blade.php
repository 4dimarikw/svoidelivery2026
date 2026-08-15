{{-- Ajax success (`status === 'ok'`, set by uiForm.submit() on a blank 200)
     has no session('status') to read — Fortify only sets that when the
     request did NOT ask for JSON. The three session-status codes below are
     the no-JS fallback only (plain POST-back after a real page reload):
     Fortify's own PROFILE_INFORMATION_UPDATED / PASSWORD_UPDATED constants
     are untranslated raw strings ('profile-information-updated' /
     'password-updated') — <x-ui.status-alert> would print them verbatim, so
     they're mapped to real copy here instead of being handed to it. --}}
@php
    $noJsStatusMessage = match (session('status')) {
        'profile-information-updated' => __('account.profile.basic_updated'),
        'account-profile-updated' => __('account.profile.details_updated'),
        'password-updated' => __('account.profile.password_updated'),
        // Привязка Telegram всегда идёт через полную перезагрузку (виджет
        // уводит браузер сам), поэтому обе ветки читаются отсюда, а не из
        // Alpine-статуса ajax-формы.
        'telegram-linked' => __('account.telegram.linked'),
        'telegram-unlinked' => __('account.telegram.unlinked'),
        default => null,
    };

    $user = auth()->user();
@endphp

<x-layouts.account active="profile" :title="__('account.nav.profile')">
    <div class="grid gap-6">
        @if ($noJsStatusMessage)
            <x-ui.alert tone="ok">{{ $noJsStatusMessage }}</x-ui.alert>
        @endif

        {{-- Section 1 — name + email, Fortify's own endpoint. --}}
        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <h2 class="mb-5 font-display text-heading-s uppercase text-ink-900">{{ __('account.profile.basic_title') }}</h2>

            <x-ui.form :action="route('user-profile-information.update')" method="PUT" mode="ajax">
                <div x-show="status === 'ok'" x-cloak class="mb-4">
                    <x-ui.alert tone="ok">{{ __('account.profile.basic_updated') }}</x-ui.alert>
                </div>

                <div class="grid gap-3.5">
                    <x-ui.input-field
                        name="name"
                        :label="__('account.field.name')"
                        :value="$user->name"
                        bag="updateProfileInformation"
                        required
                    />
                    {{-- У аккаунта, созданного через Telegram, email нет и
                         взяться ему неоткуда — required только для остальных,
                         иначе такой пользователь не смог бы поменять даже имя.
                         Серверное правило зеркалит это в
                         App\Actions\Fortify\UpdateUserProfileInformation. --}}
                    <x-ui.input-field
                        name="email"
                        type="email"
                        :label="__('account.field.email')"
                        :value="$user->email"
                        :help="$user->email === null ? __('account.telegram.email_optional') : null"
                        autocomplete="username"
                        bag="updateProfileInformation"
                        :required="! $user->telegramLinked()"
                    />

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
        </x-ui.surface>

        {{-- Section 2 — structured profile fields (Domain\Profile\Models\Profile). --}}
        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <h2 class="mb-5 font-display text-heading-s uppercase text-ink-900">{{ __('account.profile.details_title') }}</h2>

            <x-ui.form :action="route('account.profile.update')" method="PUT" mode="ajax">
                <div x-show="status === 'ok'" x-cloak class="mb-4">
                    <x-ui.alert tone="ok">{{ __('account.profile.details_updated') }}</x-ui.alert>
                </div>

                <div class="grid gap-3.5">
                    <x-ui.input-field name="last_name" :label="__('account.field.last_name')" :value="$profile?->last_name" />
                    <x-ui.input-field name="first_name" :label="__('account.field.first_name')" :value="$profile?->first_name" />
                    <x-ui.input-field name="patronymic" :label="__('account.field.patronymic')" :value="$profile?->patronymic" />
                    <x-ui.input-field
                        name="phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        x-data="phoneMask"
                        placeholder="+7 (999) 123-45-67"
                        :label="__('account.field.phone')"
                        :help="__('account.field.phone_help')"
                        :value="$profile?->phone"
                    />
                    <x-ui.input-field name="vk_url" type="url" :label="__('account.field.vk_url')" :value="$profile?->vk_url" placeholder="https://vk.com/..." />
                    <x-ui.input-field name="telegram_url" type="url" :label="__('account.field.telegram_url')" :value="$profile?->telegram_url" placeholder="https://t.me/..." />
                    <x-ui.textarea-field name="default_order_comment" :label="__('account.field.default_order_comment')" :value="$profile?->default_order_comment" />

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
        </x-ui.surface>

        {{-- Section 3 — password, Fortify's own endpoint.

             Скрыта у аккаунтов без пароля (вход только через Telegram):
             App\Actions\Fortify\UpdateUserPassword требует current_password,
             которого у них нет, а Hash::check() против null-хеша — это
             TypeError, а не чистый false. Отдельная форма «задать пароль»
             в объём этой задачи не входит. --}}
        @if ($user->password !== null)
        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <h2 class="mb-5 font-display text-heading-s uppercase text-ink-900">{{ __('account.profile.password_title') }}</h2>

            <x-ui.form :action="route('user-password.update')" method="PUT" mode="ajax">
                <div x-show="status === 'ok'" x-cloak class="mb-4">
                    <x-ui.alert tone="ok">{{ __('account.profile.password_updated') }}</x-ui.alert>
                </div>

                <div class="grid gap-3.5">
                    <x-ui.password-field
                        name="current_password"
                        :label="__('account.field.current_password')"
                        autocomplete="current-password"
                        bag="updatePassword"
                        required
                    />
                    <x-ui.password-field
                        name="password"
                        :label="__('account.field.new_password')"
                        :help="__('account.register.password_hint')"
                        autocomplete="new-password"
                        bag="updatePassword"
                        required
                    />
                    <x-ui.password-field
                        name="password_confirmation"
                        :label="__('account.field.new_password_confirmation')"
                        autocomplete="new-password"
                        bag="updatePassword"
                        required
                    />

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
        </x-ui.surface>
        @endif

        {{-- Section 4 — Telegram. Привязка не через форму: пользователь
             открывает диплинк в самом Telegram, чат находит его через
             /start (App\Telegraph\WebhookHandler) — см. ProfileController::edit(). --}}
        <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
            <h2 class="mb-5 font-display text-heading-s uppercase text-ink-900">{{ __('account.telegram.section_title') }}</h2>

            @if ($user->telegramLinked())
                <p class="mb-4 text-body-m text-ink-700">
                    {{ __('account.telegram.linked_as', ['id' => $user->telegramChat->chat_id]) }}
                </p>

                @if ($user->canUnlinkTelegram())
                    <x-ui.form :action="route('account.telegram.unlink')" method="DELETE" mode="ajax">
                        <div x-show="status === 'ok'" x-cloak class="mb-4">
                            <x-ui.alert tone="ok">{{ __('account.telegram.unlinked') }}</x-ui.alert>
                        </div>

                        <x-ui.form-actions>
                            <x-ui.btn type="submit" variant="ghost" x-bind:disabled="submitting">
                                <span x-show="!submitting">{{ __('account.telegram.unlink') }}</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center">
                                    <x-ui.spinner size="16" class="mr-2" />{{ __('account.profile.saving') }}
                                </span>
                            </x-ui.btn>
                        </x-ui.form-actions>
                    </x-ui.form>
                @else
                    <x-ui.help>{{ __('account.telegram.unlink_blocked') }}</x-ui.help>
                @endif
            @else
                <p class="mb-4 text-body-m text-ink-700">{{ __('account.telegram.not_linked') }}</p>

                @if ($telegramBotUsername)
                    <x-ui.btn href="https://t.me/{{ $telegramBotUsername }}?start={{ $telegramLinkCode }}" variant="ghost">
                        {{ __('account.telegram.open_in_telegram') }}
                    </x-ui.btn>
                @endif
            @endif
        </x-ui.surface>
    </div>
</x-layouts.account>
