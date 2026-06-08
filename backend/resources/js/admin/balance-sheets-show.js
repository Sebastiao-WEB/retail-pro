import { submitJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { showPreloader } from './preloader.js';
import { reloadWithToast } from './utils.js';

function getBalanceId() {
    const root = document.querySelector('[data-balance-id]');
    return root?.dataset.balanceId || null;
}

async function postBalanceAction(actionKey, balanceId, payload = null) {
    const response = await http.post(route(actionKey, { balanceSheet: balanceId }), payload || undefined);
    return response.data;
}

export default function init() {
    const detailForm = document.getElementById('balance-detail-form');
    const balanceId = getBalanceId();

    detailForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!balanceId) {
            return;
        }

        submitJson(detailForm, {
            method: 'PUT',
            url: route('update', { balanceSheet: balanceId }),
            onSuccess: (data) => reloadWithToast(data?.message),
        });
    });

    document.addEventListener('click', async (event) => {
        const pageLink = event.target instanceof Element ? event.target.closest('a[href][data-rp-page-nav]') : null;
        if (pageLink) {
            showPreloader();
            return;
        }

        const trigger = event.target.closest('[data-action]');
        if (!trigger || !balanceId) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'balance-recalculate') {
            try {
                const data = await postBalanceAction('recalculate', balanceId);
                reloadWithToast(data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
            return;
        }

        if (action === 'balance-finalize') {
            const form = document.getElementById('balance-detail-form');
            const payload = form ? {
                titulo: form.querySelector('[name="titulo"]')?.value,
                notas: form.querySelector('[name="notas"]')?.value,
            } : {};

            try {
                const data = await postBalanceAction('finalize', balanceId, payload);
                reloadWithToast(data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
