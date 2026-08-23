/**
 * Избранное — переключение "в избранном"/"не в избранном" на карточке
 * товара (см. resources/views/components/ui/product-card.blade.php) и
 * общий счётчик для шапки/<x-ui.account-nav>.
 * Регистрируется на `alpine:init`, как и весь остальной набор в ui.js.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Счётчик избранного, общий для всех карточек на странице — обновляется
     * из uiFavoriteToggle.toggle() при успешном ответе сервера. Начальное
     * значение сидируется с сервера (см. x-init в <x-layouts.header>).
     */
    Alpine.store('favorites', {
        count: 0,
    });

    /**
     * Состояние одной кнопки-сердечка. `favorited` сидируется с сервера
     * (FavoriteManager::has() в product-card.blade.php) — первый рендер уже
     * корректен без JS. При отключённом/упавшем JS форма отправляется
     * обычным POST, back() возвращает на ту же страницу с уже актуальным
     * состоянием (пересчитанным заново на сервере).
     */
    Alpine.data('uiFavoriteToggle', (favorited = false, productId) => ({
        favorited,
        pending: false,

        async toggle(el) {
            if (this.pending) {
                return;
            }

            this.pending = true;

            try {
                const response = await fetch(el.action, {
                    method: 'POST',
                    body: new FormData(el),
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(`Unexpected response: ${response.status}`);
                }

                const body = await response.json();
                this.favorited = body.favorited;
                Alpine.store('favorites').count = body.count;

                // Не в избранном — сигнал странице избранного убрать карточку
                // из сетки (тот же приём, что у cart:item-removed в cart.js).
                // На других страницах слушателей нет, диспатч безвреден.
                if (!body.favorited) {
                    window.dispatchEvent(new CustomEvent('favorite:removed', { detail: { productId } }));
                }
            } catch (e) {
                // Сеть недоступна — откатываемся к обычной отправке формы.
                el.submit();
            } finally {
                this.pending = false;
            }
        },
    }));
});
