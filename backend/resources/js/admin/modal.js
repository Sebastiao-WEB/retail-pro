function portalModalToBody(el) {
    if (el.parentElement !== document.body) {
        document.body.appendChild(el);
    }
}

export function portalAllModals() {
    document.querySelectorAll('.rp-admin-modal').forEach(portalModalToBody);
}

export function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    portalModalToBody(el);
    el.classList.remove('hidden');
    el.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
}

export function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    el.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.rp-admin-modal:not(.hidden)')) {
        document.body.classList.remove('overflow-hidden');
    }
}

function buildDisableLabel(trigger) {
    const code = trigger.dataset.code || '';
    const name = trigger.dataset.name || '';
    const username = trigger.dataset.username || '';

    if (name && username) {
        return `${name} (${username})`;
    }

    if (code && name) {
        return `${code} — ${name}`;
    }

    return name || code || username || '—';
}

function openDisableConfirmModal(trigger) {
    const modalId = trigger.dataset.modalTarget;
    const inputId = trigger.dataset.inputTarget;
    const labelId = trigger.dataset.labelTarget;
    const resourceId = trigger.dataset.id;

    if (!modalId || !inputId || !resourceId) {
        return;
    }

    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }

    input.value = resourceId;

    if (labelId) {
        const label = document.getElementById(labelId);
        if (label) {
            label.textContent = buildDisableLabel(trigger);
        }
    }

    openModal(modalId);
}

export function bindDisableConfirmHandlers() {
    portalAllModals();

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-action="confirm-delete"], [data-action="confirm-disable"]')
            : null;

        if (!trigger) {
            return;
        }

        openDisableConfirmModal(trigger);
    }, true);
}

export function bindModalDismiss() {
    portalAllModals();

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-modal-close]')
            : null;

        if (!button) {
            return;
        }

        const target = button.getAttribute('data-modal-close');
        if (target) {
            closeModal(target);
        }
    });

    // Importante: não fechamos o modal ao clicar no backdrop/área branca.
    // Isso garante um comportamento consistente em todas as páginas de admin.
}

export function showFieldErrors(form, errors = {}) {
    form.querySelectorAll('[data-field-error]').forEach((node) => {
        node.textContent = '';
        node.classList.add('hidden');
    });
    form.querySelectorAll('.rp-input-error').forEach((input) => {
        input.classList.remove('rp-input-error');
    });

    Object.entries(errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : String(messages || '');
        const errorNode = form.querySelector(`[data-field-error="${field}"]`);
        const input = form.querySelector(`[name="${field}"]`);
        if (errorNode) {
            errorNode.textContent = message;
            errorNode.classList.remove('hidden');
        }
        if (input) {
            input.classList.add('rp-input-error');
        }
    });
}

export function clearFieldErrors(form) {
    showFieldErrors(form, {});
}
