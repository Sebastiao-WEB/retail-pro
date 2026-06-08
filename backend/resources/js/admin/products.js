import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';
import { fillForm } from './utils.js';

const MODAL_ID = 'product-form-modal';
const FORM_ID = 'product-form';

function toggleIvaFields(form) {
    const tipo = form.querySelector('[name="iva_tipo"]')?.value || 'ISENTO';

    form.querySelectorAll('[data-iva-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.ivaPanel !== tipo);
    });
}

function setFormTitle(form, mode) {
    const title = document.getElementById('product-form-title');
    if (!title) {
        return;
    }

    title.textContent = mode === 'edit'
        ? (title.dataset.editTitle || title.textContent)
        : (title.dataset.createTitle || title.textContent);
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
    document.getElementById('product-stock-edit')?.classList.add('hidden');
    document.getElementById('product-stock-create')?.classList.remove('hidden');
    setFormTitle(form, 'create');
    toggleIvaFields(form);
}

function populateProductForm(form, product) {
    fillForm(form, product);
    form.dataset.editingId = product.id;
    document.getElementById('product-stock-edit')?.classList.remove('hidden');
    document.getElementById('product-stock-create')?.classList.add('hidden');

    const stockNode = document.getElementById('product-stock-value');
    if (stockNode) {
        stockNode.textContent = product.stock ?? '0';
    }

    setFormTitle(form, 'edit');
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
        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url: editingId ? route('update', { product: editingId }) : route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        if (trigger.dataset.action === 'open-create') {
            resetProductForm(form);
            openModal(MODAL_ID);
            return;
        }

        if (trigger.dataset.action === 'open-edit') {
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
