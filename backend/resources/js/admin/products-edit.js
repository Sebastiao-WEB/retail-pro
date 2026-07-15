function toggleIvaFields(form) {
    const tipo = form.querySelector('[name="iva_tipo"]')?.value || 'ISENTO';

    form.querySelectorAll('[data-iva-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.ivaPanel !== tipo);
    });
}

export default function init() {
    const form = document.getElementById('product-edit-form');
    if (!form) {
        return;
    }

    form.querySelector('[name="iva_tipo"]')?.addEventListener('change', () => toggleIvaFields(form));
}
