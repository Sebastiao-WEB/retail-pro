import { route } from './routes.js';
import { showPreloader } from './preloader.js';

function buildExportUrl(baseUrl) {
    const filterForm = document.querySelector('form[data-auto-submit]');
    if (!filterForm || !baseUrl) {
        return baseUrl;
    }

    const params = new URLSearchParams(new FormData(filterForm));
    const query = params.toString();

    return query ? `${baseUrl}?${query}` : baseUrl;
}

export default function init() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        if (trigger.dataset.action === 'export-csv') {
            const url = buildExportUrl(route('export'));
            if (url) {
                showPreloader();
                window.location.href = url;
            }
        }
    });
}
