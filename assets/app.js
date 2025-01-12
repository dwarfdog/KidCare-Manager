import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

import '@fortawesome/fontawesome-free/css/all.min.css';

import './js/confirmation.js';
