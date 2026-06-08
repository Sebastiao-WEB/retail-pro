export function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
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

export function bindModalDismiss() {
    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-modal-close');
            if (target) closeModal(target);
        });
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
