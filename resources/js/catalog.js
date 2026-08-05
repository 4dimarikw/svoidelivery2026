/**
 * Регистрация Alpine.data() для страницы каталога (главная, route `home`).
 * Регистрируется на `alpine:init`, чтобы порядок загрузки относительно Alpine.start() не имел значения.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Бесконечная подгрузка карточек товара через @alpinejs/intersect.
     *
     * Сервер отдаёт следующую страницу как HTML-фрагмент (те же Blade-карточки,
     * что и на первом рендере — см. pages/catalog/_cards.blade.php), а не JSON,
     * поэтому разметка карточки существует в одном месте. Ссылка на следующую
     * страницу приходит в заголовке ответа X-Next-Page (по аналогии с X-Redirect
     * в ui.js) — тело ответа при этом остаётся чистым HTML-фрагментом.
     */
    Alpine.data('catalogList', (nextPageUrl = null) => ({
        nextUrl: nextPageUrl,
        loading: false,
        error: false,

        async loadMore() {
            // Гард от повторного срабатывания x-intersect, пока предыдущий
            // запрос ещё не завершился, и от вызова после последней страницы.
            if (this.loading || !this.nextUrl) {
                return;
            }

            this.loading = true;
            this.error = false;

            try {
                const response = await fetch(this.nextUrl, {
                    headers: {
                        'X-Catalog-Partial': '1',
                        Accept: 'text/html',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Unexpected response: ${response.status}`);
                }

                this.$refs.grid.insertAdjacentHTML('beforeend', await response.text());
                this.nextUrl = response.headers.get('X-Next-Page') || null;
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
    }));
});
