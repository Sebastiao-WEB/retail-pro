import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';

const MODAL_ID = 'stock-transfer-modal';
const FORM_ID = 'stock-transfer-form';

async function refreshAvailable(form) {
    const fromLocationId = form.querySelector('[name="from_location_id"]')?.value;
    const productId = form.querySelector('[name="product_id"]')?.value;
    const availableNode = form.querySelector('[data-available-value]');

    if (!fromLocationId || !productId || !availableNode) {
        if (availableNode) {
            availableNode.textContent = '—';
        }
        return;
    }

    try {
        const url = new URL(route('available'), window.location.origin);
        url.searchParams.set('from_location_id', fromLocationId);
        url.searchParams.set('product_id', productId);

        const data = await fetchJson(url.toString());
        const disponivel = Number(data?.disponivel ?? 0);
        availableNode.textContent = disponivel.toLocaleString('pt-PT', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });
    } catch {
        availableNode.textContent = '—';
    }
}

function resetTransferForm(form) {
    form.reset();
    form.querySelector('[name="quantity"]').value = '1';
    form.querySelector('[data-available-value]').textContent = '—';
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    if (!form) {
        return;
    }

    form.querySelector('[name="from_location_id"]')?.addEventListener('change', () => refreshAvailable(form));
    form.querySelector('[name="product_id"]')?.addEventListener('change', () => refreshAvailable(form));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(form, {
            method: 'POST',
            url: route('store'),
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create') {
            resetTransferForm(form);
            openModal(MODAL_ID);
            return;
        }

        if (action === 'stock-transfer-cancel') {
            closeModal(MODAL_ID);
        }
    });
}
