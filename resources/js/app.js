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

// bfcache: кнопка «Назад» браузера часто не перезапрашивает страницу у
// сервера, а восстанавливает вкладку из памяти со снимком DOM/JS-состояния
// на момент ухода — если снимок сделан до ajax-обновления (например,
// количества в корзине через cart-stepper), пользователь видит устаревшие
// данные. Cache-Control здесь не помогает: no-store не гарантированно
// блокирует bfcache (Chrome его с 2025 разрешает, Safari — исторически
// тоже, а Safari 13.1+ в таргете проекта), no-cache/max-age=0 на bfcache
// вообще не влияют. pageshow + persisted — единственный надёжный сигнал
// именно о восстановлении из bfcache, поддерживается везде (API 2008 года).
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
