import { openModal, closeModal } from './modal.js';
import { submitJson } from './form.js';
import { route } from './routes.js';

const MODAL_ID = 'reversal-decision-modal';
const FORM_ID = 'reversal-decision-form';

function applyPeriod(start, end) {
    const form = document.querySelector('form[data-reversal-filters]');
    if (!form) {
        return;
    }

    const startInput = form.querySelector('[name="periodo_inicio"]');
    const endInput = form.querySelector('[name="periodo_fim"]');

    if (startInput) {
        startInput.value = start;
    }

    if (endInput) {
        endInput.value = end;
    }

    form.submit();
}

export default function init() {
    const form = document.getElementById(FORM_ID);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const reversalId = form.dataset.reversalId;
        if (!reversalId) {
            return;
        }

        submitJson(form, {
            method: 'POST',
            url: route('decide', { reversalRequest: reversalId }),
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'apply-this-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            applyPeriod(start.toISOString().slice(0, 10), now.toISOString().slice(0, 10));
            return;
        }

        if (action === 'apply-previous-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);
            applyPeriod(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
            return;
        }

        if (action === 'open-decision') {
            const id = trigger.dataset.id;
            const status = trigger.dataset.status;
            const reference = trigger.dataset.reference || '';

            if (!id || !status || !form) {
                return;
            }

            form.dataset.reversalId = id;
            form.querySelector('[name="decisionStatus"]').value = status;

            const label = form.querySelector('[data-decision-reference]');
            if (label) {
                label.textContent = reference;
            }

            const title = form.querySelector('[data-decision-title]');
            if (title) {
                title.textContent = status === 'APPROVED' ? 'Aprovar reversão' : 'Rejeitar reversão';
            }

            form.querySelector('[name="decisionReason"]').value = '';
            openModal(MODAL_ID);
            return;
        }

    });
}
