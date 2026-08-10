{{-- Автовход через Telegram Mini App — включается в MoonShine
     (SiteSettingsPage, Infrastructure\Settings\SiteSettings::$telegram_autologin).
     Скрытая POST-форма без визуального представления: submit'ится сама из
     Alpine (telegramAutologin, resources/js/telegram.js), когда
     window.Telegram.WebApp.initData непустая, то есть сайт правда открыт
     внутри Mini App — на обычной странице в браузере форма просто лежит
     мёртвым грузом.

     Смонтирован в <x-layouts.app>, а не только на /login — <x-ui.telegram-webapp-button>
     остаётся на форме входа как запасной вариант, если автологин не
     сработал (протухшая initData, сбой сети, JS отключён). --}}

@guest
    @php
        // Порядок важен: SiteSettings уже резолвится на каждой публичной
        // странице через LoadPublicPage (Container::scoped(), см.
        // config/settings.php) — второе чтение здесь бесплатное. Пока
        // настройка выключена (по умолчанию так и есть), TelegramBot::current()
        // вообще не дёргается — незачем на каждой гостевой странице читать
        // кеш бота ради фичи, которая всё равно выключена.
        $autologinOn = app(\Infrastructure\Settings\SiteSettings::class)->telegram_autologin;
        $enabled = $autologinOn && \Domain\Telegram\Models\TelegramBot::current()?->username;
    @endphp

    @if ($enabled)
        <form method="POST" action="{{ route('auth.telegram.webapp') }}" class="hidden" x-data="telegramAutologin">
            @csrf
            <input type="hidden" name="init_data" x-ref="initData">
            {{-- Путь + query текущего запроса, всегда с ведущим одиночным
                 '/' — формат, который TelegramLoginController::safeRedirectTarget()
                 пропускает. --}}
            <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
        </form>
    @endif
@endguest
