/**
 * Корзина — степпер количества на карточке товара/строке корзины (см.
 * resources/views/components/ui/{product-card,cart-stepper,cart-line}.blade.php)
 * и общий счётчик/сумма для шапки/страницы корзины (resources/views/pages/cart.blade.php).
 * Регистрируется на `alpine:init`, как и весь остальной набор в ui.js.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Счётчик и сумма корзины, общие для всех карточек/строк на странице —
     * обновляются из uiCartStepper.send()/uiCartRemove.send() при успешном
     * ответе сервера. Начальные значения сидируются с сервера (x-init в
     * <x-layouts.header> и pages/cart.blade.php).
     */
    Alpine.store('cart', {
        count: 0,
        amount: '',
    });

    /**
     * Степпер одной карточки/строки. `quantity` сидируется с сервера
     * (CartManager::quantityOf() в product-card.blade.php либо уже
     * загруженный CartItem::quantity в cart-line.blade.php) — первый рендер
     * уже корректен без JS. При отключённом/упавшем JS форма отправляется
     * обычным POST (со заспуфленным _method для decrease), back() возвращает
     * на ту же страницу с уже актуальным состоянием.
     *
     * Обе формы (increase и decrease, см. cart-stepper.blade.php) шлют сюда
     * один и тот же el — метод/маршрут читаются из самого элемента формы.
     */
    Alpine.data('uiCartStepper', (quantity = 0, productId) => ({
        quantity,
        pending: false,

        async send(el) {
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
                this.quantity = body.quantity;
                Alpine.store('cart').count = body.count;
                Alpine.store('cart').amount = body.amount;

                // Ушли в 0 — сообщаем странице корзины убрать строку из
                // списка (см. cart-line.blade.php); на каталоге/избранном
                // слушателя нет, там просто откатывается на «Купить».
                if (this.quantity === 0) {
                    window.dispatchEvent(new CustomEvent('cart:item-removed', { detail: { productId } }));
                }
            } catch (e) {
                // Сеть недоступна — откатываемся к обычной отправке формы.
                el.submit();
            } finally {
                this.pending = false;
            }
        },
    }));

    /**
     * Крестик-удаление строки корзины (cart-line.blade.php, design-system.html
     * §10, .cart-line .x) — убирает товар целиком независимо от количества.
     * Тот же fetch/FormData/JSON/фолбэк-на-submit приём, что и у uiCartStepper,
     * только без собственного `quantity` (после успеха товара в корзине уже
     * нет) — событие cart:item-removed слушает та же обёртка cart-line, что
     * и у степпера.
     */
    Alpine.data('uiCartRemove', (productId) => ({
        pending: false,

        async send(el) {
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
                Alpine.store('cart').count = body.count;
                Alpine.store('cart').amount = body.amount;

                window.dispatchEvent(new CustomEvent('cart:item-removed', { detail: { productId } }));
            } catch (e) {
                el.submit();
            } finally {
                this.pending = false;
            }
        },
    }));
});
