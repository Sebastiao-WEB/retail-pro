import { showPreloader } from './preloader.js';

export default function init() {
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href][data-rp-page-nav]') : null;
        if (link) {
            showPreloader();
        }
    });
}
