import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import './ui';
import './catalog';
import './favorites';
import './cart';

window.Alpine = Alpine;

Alpine.plugin(intersect);
Alpine.start();
