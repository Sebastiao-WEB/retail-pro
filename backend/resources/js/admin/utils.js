import { showPreloader } from './preloader.js';

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function reloadWithToast(message, type = 'success') {
    if (message) {
        sessionStorage.setItem('rp-toast', JSON.stringify({ message, type }));
    }

    showPreloader();
    window.location.reload();
}

export function fillForm(form, data = {}) {
    if (!form) {
        return;
    }

    form.querySelectorAll('[name]').forEach((field) => {
        const name = field.name;
        if (!name || name.endsWith('[]')) {
            return;
        }

        if (!(name in data)) {
            return;
        }

        const value = data[name];

        if (field.type === 'checkbox') {
            field.checked = Boolean(value);
            return;
        }

        if (field.type === 'radio') {
            field.checked = String(field.value) === String(value);
            return;
        }

        field.value = value ?? '';
    });

    form.querySelectorAll('input[type="checkbox"][name$="[]"]').forEach((checkbox) => {
        const baseName = checkbox.name.slice(0, -2);
        const selected = Array.isArray(data[baseName]) ? data[baseName] : [];
        checkbox.checked = selected.includes(checkbox.value);
    });
}

export function setCheckboxGroup(form, name, values = []) {
    const selected = new Set(values.map(String));
    form.querySelectorAll(`input[type="checkbox"][name="${name}[]"]`).forEach((checkbox) => {
        checkbox.checked = selected.has(checkbox.value);
    });
}

export function setFormTitle(titleId, mode) {
    const title = document.getElementById(titleId);
    if (!title) {
        return;
    }

    title.textContent = mode === 'edit'
        ? (title.dataset.editTitle || title.textContent)
        : (title.dataset.createTitle || title.textContent);
}

export function formatMoney(value, suffix = ' MT') {
    const text = typeof value === 'string' ? value : Number(value || 0).toLocaleString('pt-PT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return suffix ? `${text}${suffix}` : text;
}
