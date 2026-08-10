/**
 * Alpine.data() для переключения Login Widget / Mini App-кнопки на
 * auth/login.blade.php (см. TelegramLoginController за общей картиной).
 * Регистрируется на `alpine:init`, как остальные компоненты в ui.js.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('telegramAuth', () => ({
        inWebApp: false,

        init() {
            // window.Telegram.WebApp существует и в обычном браузере (SDK
            // грузится глобально, см. components/layouts/app.blade.php) —
            // признак Mini App не сам объект, а непустая initData.
            const webApp = window.Telegram && window.Telegram.WebApp;
            const initData = webApp && webApp.initData;

            this.inWebApp = typeof initData === 'string' && initData.length > 0;

            if (this.inWebApp && this.$refs.initData) {
                this.$refs.initData.value = initData;
            }
        },
    }));

    /**
     * Автовход для <x-ui.telegram-autologin> (смонтирован в
     * components/layouts/app.blade.php, включается в MoonShine —
     * SiteSettings::$telegram_autologin). Компонент — сам корневой <form>,
     * поэтому $el и есть форма.
     */
    Alpine.data('telegramAutologin', () => ({
        init() {
            const webApp = window.Telegram && window.Telegram.WebApp;
            const initData = webApp && webApp.initData;

            if (typeof initData !== 'string' || initData.length === 0) {
                return;
            }

            // Одна попытка на сессию Mini App. Без этого неудачный вход
            // (протухший initData) даёт цикл: POST → редирект на /login →
            // форма рендерится снова → POST… Если sessionStorage недоступен
            // (приватный режим Safari) — лучше не входить вовсе, чем
            // зациклиться; кнопка на /login остаётся запасным вариантом.
            try {
                if (window.sessionStorage.getItem('tg-autologin')) {
                    return;
                }
                window.sessionStorage.setItem('tg-autologin', '1');
            } catch (e) {
                return;
            }

            this.$refs.initData.value = initData;
            this.$el.submit();
        },
    }));
});
