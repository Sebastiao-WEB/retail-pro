import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';
const RELOAD_MODAL_ID = 'stock-reload-modal';
const ADJUST_MODAL_ID = 'stock-adjust-modal';
const RELOAD_FORM_ID = 'stock-reload-form';
const ADJUST_FORM_ID = 'stock-adjust-form';

async function refreshBalance(form) {
    const productId = form.dataset.productId;
    const locationId = form.querySelector('[name="to_location_id"]')?.value;
    const balanceNode = form.querySelector('[data-balance-value]');

    if (!productId || !locationId || !balanceNode) {
        return;
    }

    try {
        const url = new URL(route('balance'), window.location.origin);
        url.searchParams.set('product_id', productId);
        url.searchParams.set('to_location_id', locationId);

        const data = await fetchJson(url.toString());
        const saldo = Number(data?.saldo ?? 0);
        balanceNode.textContent = saldo.toLocaleString('pt-PT', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });
    } catch {
        balanceNode.textContent = '—';
    }
}

function openProductModal(modalId, formId, trigger) {
    const form = document.getElementById(formId);
    if (!form) {
        return;
    }

    const productId = trigger.dataset.id || '';
    form.dataset.productId = productId;

    const productIdInput = form.querySelector('[name="productId"]');
    if (productIdInput) {
        productIdInput.value = productId;
    }

    form.querySelector('[data-product-name]')?.replaceChildren(document.createTextNode(trigger.dataset.name || ''));

    const defaultLocation = trigger.dataset.defaultLocation || form.querySelector('[name="to_location_id"] option')?.value;
    const locationSelect = form.querySelector('[name="to_location_id"]');
    if (locationSelect && defaultLocation) {
        locationSelect.value = defaultLocation;
    }

    if (formId === RELOAD_FORM_ID) {
        form.querySelector('[name="quantity"]').value = '1';
        form.querySelector('[name="unitCost"]').value = trigger.dataset.unitCost || '0';
        form.querySelector('[name="supplier"]').value = 'Reposição Manual';
        form.querySelector('[name="note"]').value = '';
    } else {
        form.querySelector('[name="adjustmentDelta"]').value = '';
        form.querySelector('[name="unitCost"]').value = trigger.dataset.unitCost || '0';
        form.querySelector('[name="note"]').value = '';
    }

    refreshBalance(form);
    openModal(modalId);
}

export default function init() {
    const reloadForm = document.getElementById(RELOAD_FORM_ID);
    const adjustForm = document.getElementById(ADJUST_FORM_ID);

    [reloadForm, adjustForm].forEach((form) => {
        form?.querySelector('[name="to_location_id"]')?.addEventListener('change', () => refreshBalance(form));
    });

    reloadForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(reloadForm, {
            method: 'POST',
            url: route('reload'),
        });
    });

    adjustForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(adjustForm, {
            method: 'POST',
            url: route('adjust'),
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-reload') {
            openProductModal(RELOAD_MODAL_ID, RELOAD_FORM_ID, trigger);
            return;
        }

        if (action === 'open-adjust') {
            openProductModal(ADJUST_MODAL_ID, ADJUST_FORM_ID, trigger);
            return;
        }

        if (action === 'stock-reload-cancel') {
            closeModal(RELOAD_MODAL_ID);
        }

        if (action === 'stock-adjust-cancel') {
            closeModal(ADJUST_MODAL_ID);
        }
    });
}
