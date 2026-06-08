import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';
import { fillForm } from './utils.js';

const MODAL_ID = 'product-form-modal';
const FORM_ID = 'product-form';

function toggleIvaFields(form) {
    const tipo = form.querySelector('[name="iva_tipo"]')?.value || 'ISENTO';
    const percentWrap = form.querySelector('[data-iva-field="percentual"]');
    const monetarioWrap = form.querySelector('[data-iva-field="monetario"]');
    const exemptWrap = form.querySelector('[data-iva-field="isento"]');

    percentWrap?.classList.toggle('hidden', tipo !== 'PERCENTUAL');
    monetarioWrap?.classList.toggle('hidden', tipo !== 'MONETARIO');
    exemptWrap?.classList.toggle('hidden', tipo !== 'ISENTO');
}

function resetProductForm(form) {
    form.reset();
    const activeCheckbox = form.querySelector('[name="is_active"]');
    if (activeCheckbox) {
        activeCheckbox.checked = true;
    }
    form.querySelector('[name="unidade_venda"]').value = 'UN';
    form.querySelector('[name="iva_tipo"]').value = 'ISENTO';
    form.querySelector('[name="preco_compra"]').value = '0';
    form.querySelector('[name="preco_venda"]').value = '0';
    form.querySelector('[name="iva_percentual"]').value = '0';
    form.querySelector('[name="iva_valor"]').value = '0';
    form.dataset.editingId = '';
    form.querySelector('[data-form-title]')?.classList.add('hidden');
    form.querySelector('[data-form-title-create]')?.classList.remove('hidden');
    form.querySelector('[data-stock-readonly]')?.classList.add('hidden');
    form.querySelector('[data-stock-create]')?.classList.remove('hidden');
    toggleIvaFields(form);
}

function populateProductForm(form, product) {
    fillForm(form, product);
    form.dataset.editingId = product.id;
    form.querySelector('[data-form-title]')?.classList.remove('hidden');
    form.querySelector('[data-form-title-create]')?.classList.add('hidden');
    form.querySelector('[data-stock-readonly]')?.classList.remove('hidden');
    form.querySelector('[data-stock-create]')?.classList.add('hidden');

    const stockNode = form.querySelector('[data-stock-value]');
    if (stockNode) {
        stockNode.textContent = product.stock ?? '0';
    }

    toggleIvaFields(form);
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    if (!form) {
        return;
    }

    form.querySelector('[name="iva_tipo"]')?.addEventListener('change', () => toggleIvaFields(form));

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const editingId = form.dataset.editingId;
        const url = editingId
            ? route('update', { product: editingId })
            : route('store');

        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url,
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create') {
            resetProductForm(form);
            openModal(MODAL_ID);
            return;
        }

        if (action === 'open-edit') {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            try {
                const product = await fetchJson(route('show', { product: id }));
                resetProductForm(form);
                populateProductForm(form, product);
                openModal(MODAL_ID);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }

    });
}
