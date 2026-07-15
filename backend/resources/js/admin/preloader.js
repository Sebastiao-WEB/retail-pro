const PRELOADER_ID = 'rp-admin-preloader';

let activeRequests = 0;
let pageReady = false;

function getPreloader() {
    return document.getElementById(PRELOADER_ID);
}

export function showPreloader() {
    const preloader = getPreloader();
    if (!preloader) {
        return;
    }

    preloader.classList.remove('hidden');
    preloader.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rp-preloader-active');
}

export function hidePreloader() {
    if (!pageReady || activeRequests > 0) {
        return;
    }

    const preloader = getPreloader();
    if (!preloader) {
        return;
    }

    preloader.classList.add('hidden');
    preloader.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('rp-preloader-active');
}

export function trackRequestStart() {
    activeRequests += 1;
    showPreloader();
}

export function trackRequestEnd() {
    activeRequests = Math.max(0, activeRequests - 1);
    hidePreloader();
}

function isInternalNavigationLink(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (link.hasAttribute('data-rp-ignore-preloader') || link.hasAttribute('download')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
        return false;
    }

    try {
        const url = new URL(link.href, window.location.origin);
        return url.origin === window.location.origin;
    } catch {
        return false;
    }
}

function wrapFetchForPreloader() {
    if (window.__rpPreloaderFetchWrapped) {
        return;
    }

    window.__rpPreloaderFetchWrapped = true;
    const originalFetch = window.fetch.bind(window);

    window.fetch = async (...args) => {
        trackRequestStart();
        try {
            return await originalFetch(...args);
        } finally {
            trackRequestEnd();
        }
    };
}

export function bindAdminPreloader() {
    if (!document.body.dataset.adminPage) {
        return;
    }

    wrapFetchForPreloader();
    showPreloader();

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!isInternalNavigationLink(link)) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        showPreloader();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.hasAttribute('data-rp-ignore-preloader') || form.dataset.autoSubmit !== undefined) {
            return;
        }

        showPreloader();
    });
}

export function markAdminPageReady() {
    pageReady = true;
    hidePreloader();
}
