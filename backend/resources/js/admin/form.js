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

function buildFilterUrl(form) {
    const action = form.getAttribute('action') || window.location.pathname;
    const url = new URL(action, window.location.origin);
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        const raw = String(value ?? '');
        // Não fazer trim no valor enviado — o utilizador pode estar a escrever um espaço
        // no meio do termo (ex.: "Agua mineral").
        if (raw.trim() === '') {
            return;
        }
        params.append(key, raw);
    });

    url.search = params.toString();

    return url;
}

/** Valores de texto mais recentes, para não perder espaços ao substituir o HTML. */
const latestTextFieldValues = {};

function captureTextFieldValues(form) {
    const values = {};
    form.querySelectorAll('input[type="text"], input[type="search"], textarea').forEach((input) => {
        if (!input.name) {
            return;
        }
        values[input.name] = input.value;
        latestTextFieldValues[input.name] = input.value;
    });
    return values;
}

function restoreTextFieldValues(values) {
    const merged = { ...values, ...latestTextFieldValues };
    Object.entries(merged).forEach(([name, value]) => {
        const field = document.querySelector(`[name="${CSS.escape(name)}"]`);
        if (
            field instanceof HTMLInputElement ||
            field instanceof HTMLTextAreaElement
        ) {
            field.value = value;
        }
    });
}

function captureFocusState() {
    const active = document.activeElement;
    if (!(active instanceof HTMLInputElement || active instanceof HTMLTextAreaElement || active instanceof HTMLSelectElement)) {
        return null;
    }

    if (active.name) {
        latestTextFieldValues[active.name] = active.value;
    }

    return {
        name: active.getAttribute('name'),
        id: active.id || null,
        value: active.value,
        selectionStart: typeof active.selectionStart === 'number' ? active.selectionStart : null,
        selectionEnd: typeof active.selectionEnd === 'number' ? active.selectionEnd : null,
    };
}

function restoreFocusState(state) {
    if (!state) {
        return;
    }

    let field = null;
    if (state.id) {
        field = document.getElementById(state.id);
    }
    if (!field && state.name) {
        field = document.querySelector(`[name="${CSS.escape(state.name)}"]`);
    }
    if (!(field instanceof HTMLElement)) {
        return;
    }

    if (
        (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) &&
        state.name &&
        Object.prototype.hasOwnProperty.call(latestTextFieldValues, state.name)
    ) {
        field.value = latestTextFieldValues[state.name];
    } else if (
        (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) &&
        typeof state.value === 'string'
    ) {
        field.value = state.value;
    }

    field.focus({ preventScroll: true });
    if (
        (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) &&
        state.selectionStart !== null &&
        state.selectionEnd !== null &&
        typeof field.setSelectionRange === 'function' &&
        field.type !== 'checkbox' &&
        field.type !== 'radio'
    ) {
        try {
            const length = field.value.length;
            const start = Math.min(state.selectionStart, length);
            const end = Math.min(state.selectionEnd, length);
            field.setSelectionRange(start, end);
        } catch {
            // ignore unsupported input types
        }
    }
}

function isRequestCanceled(error) {
    const original = error?.original;
    return (
        error?.code === 'ERR_CANCELED' ||
        original?.code === 'ERR_CANCELED' ||
        original?.name === 'CanceledError' ||
        original?.name === 'AbortError'
    );
}

function refreshAdminIcons() {
    if (typeof window.rpRefreshIcons === 'function') {
        window.rpRefreshIcons();
        return;
    }

    window.dispatchEvent(new CustomEvent('rp:admin-content-updated'));
}

let autoSubmitSeq = 0;
const autoSubmitControllers = new WeakMap();

async function ajaxFilterSubmit(form) {
    const seq = ++autoSubmitSeq;
    const typedValues = captureTextFieldValues(form);
    const focusState = captureFocusState();
    const url = buildFilterUrl(form);

    const previous = autoSubmitControllers.get(form);
    previous?.abort();

    const controller = new AbortController();
    autoSubmitControllers.set(form, controller);

    try {
        const response = await http.get(url.toString(), {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            responseType: 'text',
            transformResponse: [(data) => data],
            skipPreloader: true,
            signal: controller.signal,
        });

        if (seq !== autoSubmitSeq) {
            return;
        }

        const html = typeof response.data === 'string' ? response.data : String(response.data ?? '');
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextCard = doc.querySelector('.rp-card');
        const currentCard = document.querySelector('.rp-card');

        if (!nextCard || !currentCard) {
            window.location.assign(url.toString());
            return;
        }

        currentCard.innerHTML = nextCard.innerHTML;
        history.replaceState({}, '', url.toString());
        refreshAdminIcons();
        bindAutoSubmitForms();
        restoreTextFieldValues(typedValues);
        restoreFocusState(focusState);
        window.dispatchEvent(new CustomEvent('rp:admin-content-updated'));
    } catch (error) {
        if (isRequestCanceled(error) || seq !== autoSubmitSeq) {
            return;
        }

        window.retailToast?.(error.message || 'Falha ao actualizar a pesquisa.', 'error');
    }
}

export function bindAutoSubmitForms() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        if (form.dataset.autoSubmitBound === '1') {
            return;
        }

        form.dataset.autoSubmitBound = '1';
        const debounceMs = Number(form.dataset.debounce || 300);
        let timer = null;

        const submitAjax = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                void ajaxFilterSubmit(form);
            }, debounceMs);
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            clearTimeout(timer);
            void ajaxFilterSubmit(form);
        });

        form.querySelectorAll('input[type="text"], input[type="search"]').forEach((input) => {
            input.addEventListener('input', () => {
                if (input.name) {
                    latestTextFieldValues[input.name] = input.value;
                }
                submitAjax();
            });
        });

        form.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach((element) => {
            element.addEventListener('change', () => {
                clearTimeout(timer);
                void ajaxFilterSubmit(form);
            });
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
