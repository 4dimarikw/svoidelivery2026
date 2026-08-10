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
});
