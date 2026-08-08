<x-layouts.app title="UI Kit — Свои Delivery">
    <main id="main" class="mx-auto max-w-page px-6 py-12">
        <h1 class="mb-8 font-display text-display-l uppercase text-ink-900">UI Kit</h1>

        {{-- ————————————————————————————————————————— Buttons ————— --}}
        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Buttons</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.btn>Primary</x-ui.btn>
                <x-ui.btn variant="ghost">Ghost</x-ui.btn>
                <x-ui.btn variant="cream">Cream</x-ui.btn>
                <x-ui.btn variant="rust">Rust</x-ui.btn>
                <x-ui.btn variant="link">Link</x-ui.btn>
                <x-ui.btn disabled>Disabled</x-ui.btn>
                <x-ui.btn loading>Loading</x-ui.btn>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3 rounded-sm bg-teal-900 p-4 on-dark">
                <x-ui.btn variant="ghost-light">Ghost light</x-ui.btn>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-ui.btn size="sm">Small</x-ui.btn>
                <x-ui.btn size="md">Medium</x-ui.btn>
                <x-ui.btn size="lg">Large</x-ui.btn>
                <x-ui.btn href="#">As link</x-ui.btn>
            </div>
        </section>

        {{-- ————————————————————————————————————————— Icons ————— --}}
        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Icons</h2>
            <div class="flex flex-wrap gap-4">
                @foreach (['eye', 'eye-off', 'check', 'alert-circle', 'alert-triangle', 'info', 'x', 'arrow-left', 'loader', 'copy', 'mail', 'lock', 'shield'] as $iconName)
                    <div class="flex flex-col items-center gap-1 text-ink-700">
                        <x-ui.icon :name="$iconName" :label="$iconName" />
                        <span class="font-mono text-micro text-ink-500">{{ $iconName }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ————————————————————————————————————————— Chips / badges ————— --}}
        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Chips &amp; badges</h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.chip dot>В наличии</x-ui.chip>
                <x-ui.chip tone="teal">Холодное</x-ui.chip>
                <x-ui.chip tone="cream">0,5 л</x-ui.chip>
                <x-ui.chip tone="rust">Новинка</x-ui.chip>
                <x-ui.chip tone="solid">Свои выбирают</x-ui.chip>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-ui.badge tone="ok">Доставлен</x-ui.badge>
                <x-ui.badge tone="way">В пути</x-ui.badge>
                <x-ui.badge tone="cancel">Отменён</x-ui.badge>
            </div>
        </section>

        {{-- ————————————————————————————————————————— Breadcrumbs ————————— --}}
        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Breadcrumbs</h2>
            <x-ui.breadcrumbs :items="[
                ['label' => 'Каталог', 'url' => '#'],
                ['label' => 'Пиво', 'url' => '#'],
                ['label' => 'Hop Machine DDH IPA'],
            ]" />
        </section>

        {{-- ————————————————————————————————————————— Alerts ————— --}}
        <section class="mb-12 grid gap-3">
            <h2 class="font-mono text-label uppercase text-ink-500">Alerts</h2>
            <x-ui.alert tone="ok" title="Готово!">Ссылка для сброса пароля отправлена на почту.</x-ui.alert>
            <x-ui.alert tone="warn" title="Проверьте возраст.">Для заказа нужно подтвердить, что вам есть 18 лет.</x-ui.alert>
            <x-ui.alert tone="err" title="Не получилось войти.">Неверный пароль. Осталось 2 попытки.</x-ui.alert>
            <x-ui.alert tone="info" dismissible>Мы позвоним для подтверждения заказа — трубку брать не нужно.</x-ui.alert>
        </section>

        {{-- ————————————————————————————————————————— Standalone controls ————— --}}
        <section class="mb-12 grid max-w-md gap-4">
            <h2 class="font-mono text-label uppercase text-ink-500">Controls</h2>

            <x-ui.input-field name="demo_email" label="E-mail" type="email" placeholder="you@mail.ru" />
            <x-ui.input-field name="demo_readonly" label="Только чтение" value="ул. Беговая, 7" readonly />

            {{-- Path B demo: x-model on a text-like control gets rewritten to
                 x-model.fill and the server value keeps rendering — reload
                 this page and the input below should still read "Свои".
                 Inspect the element: the attribute in the DOM is
                 x-model.fill="draft", not x-model. --}}
            <div x-data="{ draft: '' }">
                <x-ui.field label="Path B — x-model.fill" for="f-demo-fill">
                    <x-ui.input name="demo_fill" id="f-demo-fill" value="Свои" x-model="draft" />
                </x-ui.field>
                <p class="mt-1.5 text-micro text-ink-500" x-text="'Alpine state: ' + draft"></p>
            </div>
            <x-ui.textarea-field name="demo_note" label="Комментарий" help="Например: этаж и подъезд" />
            <x-ui.select-field name="demo_city" label="Город" :options="['msk' => 'Москва', 'spb' => 'Санкт-Петербург']" placeholder="Выберите город" />
            <x-ui.checkbox-field name="demo_remember" label="Запомнить меня" checked />
            <x-ui.password-field name="demo_password" label="Пароль" />

            <x-ui.field label="Способ оплаты">
                <div class="grid gap-2.5">
                    <x-ui.radio name="demo_pay" value="card" checked label="Картой онлайн" />
                    <x-ui.radio name="demo_pay" value="courier" label="Картой курьеру" />
                    <x-ui.radio name="demo_pay" value="cash" label="Наличными" />
                </div>
            </x-ui.field>

            <x-ui.field label="Код из СМС">
                <x-ui.otp-input name="demo_otp" length="6" />
            </x-ui.field>

            {{-- Deliberately forced invalid, to preview the error state without a POST. --}}
            <x-ui.field label="С ошибкой" for="f-demo-invalid" help="Введите номер полностью — 11 цифр">
                <x-ui.input name="demo_invalid" id="f-demo-invalid" invalid value="+7 (9" />
            </x-ui.field>

            <x-ui.divider>или</x-ui.divider>
            <x-ui.spinner />
        </section>

        {{-- ————————————————————————————————————————— Auth forms (live) ————— --}}
        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Auth forms — mode="default"</h2>
            <x-ui.auth-card title="С возвращением" subtitle="Войдите, чтобы заказывать как свои">
                <x-ui.form action="{{ route('ui-kit.submit') }}" mode="default">
                    <div class="grid gap-3.5">
                        <x-ui.input-field name="email" label="E-mail" type="email" placeholder="you@mail.ru" autocomplete="username" required />
                        <x-ui.password-field name="password" label="Пароль" required />
                        <x-ui.checkbox-field name="agree" label="Мне есть 18 лет, принимаю условия сервиса" />
                        <x-ui.form-actions>
                            <x-ui.btn type="submit" size="lg" block>Войти</x-ui.btn>
                        </x-ui.form-actions>
                    </div>
                </x-ui.form>
                <x-slot:footer>
                    <x-ui.status-alert />
                </x-slot:footer>
            </x-ui.auth-card>
        </section>

        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Auth forms — mode="enhance" (x-model, Path C)</h2>
            <x-ui.auth-card title="Создать аккаунт">
                <x-ui.form action="{{ route('ui-kit.submit') }}" mode="enhance" :state="['agree' => (bool) old('agree')]">
                    <div class="grid gap-3.5">
                        <x-ui.input-field name="email" label="E-mail" type="email" placeholder="you@mail.ru" required />
                        <x-ui.password-field name="password" label="Пароль" autocomplete="new-password" required />

                        {{-- Path C by hand: checkbox can't use x-model.fill (see
                             CLAUDE.md), so its state is seeded from PHP via the
                             form's :state prop and bound directly, not through
                             <x-ui.checkbox-field>. --}}
                        <label class="flex items-center text-body-m text-ink-700 cursor-pointer">
                            <input type="checkbox" name="agree" value="1" x-model="state.agree" class="check-control">
                            <span class="ml-2.5">Мне есть 18 лет, принимаю условия сервиса</span>
                        </label>
                        <p class="text-micro text-ink-500" x-text="'agree = ' + state.agree"></p>
                        <x-ui.form-actions>
                            <x-ui.btn type="submit" size="lg" block>Создать аккаунт</x-ui.btn>
                        </x-ui.form-actions>
                    </div>
                </x-ui.form>
            </x-ui.auth-card>
        </section>

        <section class="mb-12">
            <h2 class="mb-4 font-mono text-label uppercase text-ink-500">Auth forms — mode="ajax"</h2>
            <x-ui.auth-card title="Войти">
                <x-ui.form action="{{ route('ui-kit.submit') }}" mode="ajax">
                    <div class="grid gap-3.5" x-show="status !== 'ok'">
                        <x-ui.input-field name="email" label="E-mail" type="email" placeholder="you@mail.ru" required />
                        <x-ui.password-field name="password" label="Пароль" required />
                        <x-ui.checkbox-field name="agree" label="Мне есть 18 лет, принимаю условия сервиса" />
                        <x-ui.form-actions>
                            <x-ui.btn type="submit" size="lg" block x-bind:disabled="submitting">
                                <span x-show="!submitting">Войти</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center">
                                    <x-ui.spinner size="16" class="mr-2" />Входим…
                                </span>
                            </x-ui.btn>
                        </x-ui.form-actions>
                    </div>
                    <p x-show="status === 'ok'" x-cloak class="text-body-m text-teal-800">Готово.</p>
                </x-ui.form>
            </x-ui.auth-card>
        </section>

        {{-- ————————————————————————————————————————— 2FA preview ————— --}}
        <section class="mb-12 grid max-w-md gap-6">
            <h2 class="font-mono text-label uppercase text-ink-500">Two-factor (invented, no design-system source)</h2>

            <x-ui.qr-panel alt="QR-код для настройки приложения-аутентификатора" secret="JBSWY3DPEHPK3PXP">
                {{-- Placeholder: Fortify's real twoFactorQrCodeSvg() output goes here. --}}
                <svg width="160" height="160" viewBox="0 0 160 160"><rect width="160" height="160" fill="#fff" /><rect x="10" y="10" width="30" height="30" fill="#1A1410" /><rect x="120" y="10" width="30" height="30" fill="#1A1410" /><rect x="10" y="120" width="30" height="30" fill="#1A1410" /></svg>
            </x-ui.qr-panel>

            <x-ui.code-list :codes="['A1B2-C3D4', 'E5F6-G7H8', 'I9J0-K1L2', 'M3N4-O5P6', 'Q7R8-S9T0', 'U1V2-W3X4']" />

            <x-ui.resend-button :seconds="10" action="{{ route('ui-kit.submit') }}" label="Отправить код ещё раз" />
        </section>
    </main>
</x-layouts.app>
