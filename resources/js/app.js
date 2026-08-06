import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import './ui';
import './catalog';
import './favorites';

window.Alpine = Alpine;

Alpine.plugin(intersect);
Alpine.start();
