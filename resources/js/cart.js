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
     * Отдельный стор под submit-кнопку и плашку ошибки формы оформления
     * (pages/cart.blade.php) — обе физически лежат в sticky-панели итога,
     * вне <form> (кнопка привязана HTML5-атрибутом form="checkout-form",
     * тот же приём, что кнопка "+" в cart-stepper.blade.php). `submitting`/
     * ошибка 'checkout' — состояние uiForm (resources/js/ui.js), которое
     * живёт в x-data самой формы; Alpine-scope наследуется по ДОМ-дереву, а
     * не по вложенности Blade-компонентов (в отличие от @aware), так что
     * дочерний относительно <x-ui.form> в разметке — не то же самое, что
     * дочерний в реальном ДОМ: раз панель итога — сосед формы, элементы
     * внутри нее не видят её errorFor()/submitting напрямую (обращение к
     * errorFor() кинуло бы ReferenceError). Форма зеркалит оба значения
     * сюда через x-effect, кнопка/алерт читают уже отсюда.
     * Alpine.$data(document.getElementById(...)) сюда не годится — зависит
     * от того, успела ли форма гидратироваться раньше кнопки/алерта.
     */
    Alpine.store('checkout', {
        submitting: false,
        error: null,
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
        error: null,

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

                // Товар кончился уже после рендера страницы — клампинг в
                // CartManager не дал ничего добавить, CartController отвечает
                // 422 (см. rejectUnavailable()). Обрабатываем до !response.ok,
                // иначе упадём в catch и откатимся на нативный el.submit().
                if (response.status === 422) {
                    const body = await response.json().catch(() => ({}));
                    this.error = body.message ?? null;
                    return;
                }

                if (!response.ok) {
                    throw new Error(`Unexpected response: ${response.status}`);
                }

                const body = await response.json();
                this.error = null;
                this.quantity = body.quantity;
                Alpine.store('cart').count = body.count;
                Alpine.store('cart').amount = body.amount;

                // Ушли в 0 — сообщаем странице корзины убрать строку из
                // списка (см. cart-line.blade.php); на каталоге/избранном
                // слушателя нет, там просто откатывается на «Купить».
                if (this.quantity === 0) {
                    window.dispatchEvent(new CustomEvent('cart:item-removed', { detail: { productId } }));
                } else {
                    // Строка корзины (cart-line.blade.php) показывает и
                    // «2 × ₽ 400» (количество × цена за штуку), и сумму
                    // строки ниже — степпер и строка сидят в разных Alpine-
                    // scope, состоянием напрямую не делятся, поэтому оба
                    // значения идут тем же событийным приёмом, что и
                    // cart:item-removed.
                    window.dispatchEvent(new CustomEvent('cart:item-updated', {
                        detail: { productId, quantity: body.quantity, amount: body.lineAmount },
                    }));
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
