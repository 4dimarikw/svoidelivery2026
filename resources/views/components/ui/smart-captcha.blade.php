{{-- Yandex SmartCaptcha (Infrastructure\Rules\SmartCaptchaRule). Ничего не
     рендерит, пока config('security.smart_captcha.enabled') выключен —
     тот же приём самоскрытия, что у <x-ui.telegram-login-button>.

     Скрипт — прямо здесь, не в layouts/app.blade.php/layouts/auth.blade.php:
     капча нужна ровно одной форме, тянуть её на каждую страницу сайта
     незачем. В проекте нет @push/@stack ни в одном файле — заводить его
     ради единственного скрипта было бы лишним усложнением. captcha.js сам
     инициализирует любой .smart-captcha[data-sitekey] на странице, никакого
     связующего JS не нужно.

     CSP сейчас не эмитится (spatie/laravel-csp установлен, но config/csp.php
     не опубликован и middleware не зарегистрировано). Если его когда-нибудь
     включат — понадобится добавить smartcaptcha.cloud.yandex.ru /
     captcha-api.yandex.ru в script-src/frame-src, иначе виджет молча
     перестанет грузиться (см. CLAUDE.md, тот же нюанс уже описан для
     telegram.org). --}}
@if (config('security.smart_captcha.enabled'))
    <div
        class="smart-captcha"
        data-sitekey="{{ config('security.smart_captcha.client_key') }}"
        data-hl="{{ app()->getLocale() }}"
    ></div>
    <x-ui.error name="smart-token" />

    <script src="https://smartcaptcha.cloud.yandex.ru/captcha.js" defer></script>
@endif
