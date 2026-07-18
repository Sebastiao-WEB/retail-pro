/**
 * Mostra/esconde painéis de IVA conforme o tipo seleccionado.
 * Delegação de eventos: continua a funcionar após pesquisa AJAX / reabertura do modal.
 */

export function toggleIvaFields(root = document) {
    const forms = root instanceof HTMLFormElement
        ? [root]
        : Array.from(root.querySelectorAll('form')).filter((form) => form.querySelector('[name="iva_tipo"]'));

    forms.forEach((form) => {
        const select = form.querySelector('[name="iva_tipo"]');
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const tipo = String(select.value || 'ISENTO').trim().toUpperCase();

        form.querySelectorAll('[data-iva-panel]').forEach((panel) => {
            if (!(panel instanceof HTMLElement)) {
                return;
            }
            const panelTipo = String(panel.dataset.ivaPanel || '').trim().toUpperCase();
            const activo = panelTipo === tipo;
            panel.classList.toggle('hidden', !activo);
            panel.setAttribute('aria-hidden', activo ? 'false' : 'true');
        });
    });
}

let bound = false;

export function bindIvaTipoToggle() {
    if (bound) {
        return;
    }
    bound = true;

    const sync = (event) => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement) || target.name !== 'iva_tipo') {
            return;
        }
        const form = target.closest('form');
        if (form) {
            toggleIvaFields(form);
        }
    };

    document.addEventListener('change', sync, true);
    document.addEventListener('input', sync, true);
}

export function initIvaFields(root = document) {
    bindIvaTipoToggle();
    toggleIvaFields(root);
}
