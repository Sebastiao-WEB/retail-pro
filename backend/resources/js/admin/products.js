import { openModal } from './modal.js';
import { submitJson } from './form.js';
import { route } from './routes.js';

const MODAL_ID = 'product-form-modal';
const FORM_ID = 'product-form';

function toggleIvaFields(form) {
    const tipo = form.querySelector('[name="iva_tipo"]')?.value || 'ISENTO';

    form.querySelectorAll('[data-iva-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.ivaPanel !== tipo);
    });
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
    const controlaEstoqueCheckbox = form.querySelector('[name="controla_estoque"]');
    if (controlaEstoqueCheckbox) {
        controlaEstoqueCheckbox.checked = true;
    }
    toggleStockCreatePanel(form);
    toggleIvaFields(form);
}

function toggleStockCreatePanel(form) {
    const controla = form.querySelector('[name="controla_estoque"]')?.checked ?? true;
    const note = form.querySelector('[data-stock-create-note]');
    if (!note) return;
    note.textContent = controla
        ? note.dataset.stockNoteDefault || note.textContent
        : note.dataset.stockNoteSkip || 'Produto sob pedido — stock não aplicável.';
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    if (!form) {
        return;
    }

    form.querySelector('[name="iva_tipo"]')?.addEventListener('change', () => toggleIvaFields(form));
    form.querySelector('[name="controla_estoque"]')?.addEventListener('change', () => toggleStockCreatePanel(form));

    const note = form.querySelector('[data-stock-create-note]');
    if (note && !note.dataset.stockNoteDefault) {
        note.dataset.stockNoteDefault = note.textContent.trim();
        note.dataset.stockNoteSkip = 'Produto sob pedido — stock não aplicável.';
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        submitJson(form, {
            method: 'POST',
            url: route('store'),
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger || trigger.dataset.action !== 'open-create') {
            return;
        }

        resetProductForm(form);
        openModal(MODAL_ID);
    });
}
