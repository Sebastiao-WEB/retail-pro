/**
 * Route templates are set on [data-routes] (JSON object).
 * Values use __ID__ as placeholder for resource ids.
 */
export function getRoutes() {
    const root = document.querySelector('[data-routes]');
    const raw = root?.getAttribute('data-routes');
    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw);
    } catch {
        const decoded = document.createElement('textarea');
        decoded.innerHTML = raw;
        try {
            return JSON.parse(decoded.value);
        } catch {
            return {};
        }
    }
}

export function routeUrl(template, params = {}) {
    if (!template) {
        return null;
    }

    let url = template;

    for (const [key, value] of Object.entries(params)) {
        url = url.replaceAll(`{${key}}`, encodeURIComponent(value ?? ''));
    }

    const id = params.id ?? Object.values(params).find((value) => value !== undefined && value !== '');
    if (id !== undefined && id !== '') {
        url = url.replaceAll('__ID__', encodeURIComponent(String(id)));
    }

    return url;
}

export function route(name, params = {}) {
    return routeUrl(getRoutes()[name], params);
}
