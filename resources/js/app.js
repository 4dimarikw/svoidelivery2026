import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import './ui';
import './telegram';
import './catalog';
import './favorites';
import './cart';

window.Alpine = Alpine;

Alpine.plugin(intersect);
Alpine.start();

// При восстановлении страницы из bfcache DOM может содержать неактуальные данные корзины.
// Событие pageshow с persisted позволяет обновить страницу; HTTP-заголовки кеша
// не гарантируют запрета восстановления DOM из памяти браузера.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
