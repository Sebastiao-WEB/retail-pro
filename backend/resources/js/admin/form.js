import http from './http.js';
import { clearFieldErrors, showFieldErrors } from './modal.js';
import { showPreloader } from './preloader.js';

export function formDataToObject(form) {
    const fd = new FormData(form);
    const data = {};

    for (const [key, value] of fd.entries()) {
        if (key.endsWith('[]')) {
            const normalizedKey = key.slice(0, -2);
            if (!data[normalizedKey]) {
                data[normalizedKey] = [];
            }
            data[normalizedKey].push(value);
            continue;
        }

        if (data[key] !== undefined) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
            continue;
        }

        data[key] = value;
    }

    form.querySelectorAll('input[type="checkbox"][name]').forEach((checkbox) => {
        if (!checkbox.name) {
            return;
        }

        data[checkbox.name] = checkbox.checked;
    });

    return data;
}

export async function submitJson(form, { method = 'POST', url, onSuccess, reload = true }) {
    clearFieldErrors(form);

    try {
        const response = await http.request({
            method,
            url,
            data: formDataToObject(form),
        });

        if (typeof onSuccess === 'function') {
            await onSuccess(response.data);
        } else if (reload) {
            const message = response.data?.message;
            if (message) {
                sessionStorage.setItem('rp-toast', JSON.stringify({ message, type: 'success' }));
            }
            showPreloader();
            window.location.reload();
        }
    } catch (error) {
        if (error.errors) {
            showFieldErrors(form, error.errors);
        }

        window.retailToast?.(error.message, 'error');
    }
}

export function bindAutoSubmitForms() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        const debounceMs = Number(form.dataset.debounce || 300);
        let timer = null;

        const submit = () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), debounceMs);
        };

        form.querySelectorAll('input[type="text"], input[type="search"]').forEach((input) => {
            input.addEventListener('input', submit);
        });

        form.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach((element) => {
            element.addEventListener('change', () => form.submit());
        });
    });
}

export function showStoredToast() {
    const raw = sessionStorage.getItem('rp-toast');
    if (!raw) {
        return;
    }

    sessionStorage.removeItem('rp-toast');

    try {
        const payload = JSON.parse(raw);
        if (payload?.message) {
            window.retailToast?.(payload.message, payload.type || 'success');
        }
    } catch {
        // ignore malformed toast payload
    }
}

export async function fetchJson(url) {
    const response = await http.get(url);
    return response.data?.data ?? response.data;
}
